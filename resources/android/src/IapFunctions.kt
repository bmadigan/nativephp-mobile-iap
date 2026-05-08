package com.bmadigan.plugins.iap

import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.fragment.app.FragmentActivity
import com.android.billingclient.api.*
import com.nativephp.mobile.bridge.BridgeError
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONArray
import org.json.JSONObject
import java.text.SimpleDateFormat
import java.util.*

object IapFunctions {
    private const val TAG = "IapFunctions"

    class CanMakePayments(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val manager = IapBillingManager.getInstance(activity)
            var canMake = false
            val latch = java.util.concurrent.CountDownLatch(1)
            manager.ensureConnected { ready -> canMake = ready; latch.countDown() }
            latch.await(5, java.util.concurrent.TimeUnit.SECONDS)
            return mapOf("canMakePayments" to canMake)
        }
    }

    class RegisterProducts(private val activity: FragmentActivity) : BridgeFunction {
        @Suppress("UNCHECKED_CAST")
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val products = parameters["products"] as? Map<String, Map<String, Any>>
                ?: throw BridgeError.InvalidParameters("products dictionary is required")
            IapBillingManager.getInstance(activity).registerProducts(products)
            return emptyMap()
        }
    }

    class GetProducts(private val activity: FragmentActivity) : BridgeFunction {
        @Suppress("UNCHECKED_CAST")
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val productIds = parameters["productIds"] as? List<String>
                ?: throw BridgeError.InvalidParameters("productIds array is required")
            if (productIds.isEmpty()) throw BridgeError.InvalidParameters("productIds must not be empty")
            val id = parameters["id"] as? String
            val eventClass = parameters["event"] as? String
                ?: "Native\\Mobile\\Iap\\Events\\ProductsLoaded"
            val manager = IapBillingManager.getInstance(activity)
            Handler(Looper.getMainLooper()).post {
                manager.queryProducts(productIds) { productDetailsList, invalidIds ->
                    val productsArray = JSONArray()
                    for (details in productDetailsList) {
                        productsArray.put(productDetailsToJson(details, manager))
                    }
                    val invalidIdsArray = JSONArray()
                    for (invalidId in invalidIds) invalidIdsArray.put(invalidId)
                    val payload = JSONObject().apply {
                        put("products", productsArray); put("invalidIds", invalidIdsArray)
                        if (id != null) put("id", id)
                    }
                    NativeActionCoordinator.dispatchEvent(activity, eventClass, payload.toString())
                }
            }
            return mapOf("status" to "success")
        }
    }

    class Purchase(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val productId = parameters["productId"] as? String
                ?: throw BridgeError.InvalidParameters("productId is required")
            val id = parameters["id"] as? String
            val eventClass = parameters["event"] as? String
                ?: "Native\\Mobile\\Iap\\Events\\PurchaseCompleted"
            val manager = IapBillingManager.getInstance(activity)
            if (manager.getCachedProduct(productId) == null) {
                manager.queryProducts(listOf(productId)) { _, _ ->
                    launchPurchase(activity, manager, productId, id, eventClass)
                }
            } else {
                Handler(Looper.getMainLooper()).post {
                    launchPurchase(activity, manager, productId, id, eventClass)
                }
            }
            return mapOf("status" to "success")
        }

        private fun launchPurchase(
            activity: FragmentActivity, manager: IapBillingManager,
            productId: String, id: String?, eventClass: String
        ) {
            Handler(Looper.getMainLooper()).post {
                manager.purchase(activity, productId) { billingResult, purchases ->
                    when (billingResult.responseCode) {
                        BillingClient.BillingResponseCode.OK -> {
                            val purchase = purchases?.firstOrNull { productId in it.products }
                            if (purchase != null) {
                                if (purchase.purchaseState == com.android.billingclient.api.Purchase.PurchaseState.PENDING) {
                                    val pendingEvent = "Native\\Mobile\\Iap\\Events\\PurchasePending"
                                    val payload = JSONObject().apply {
                                        put("productId", productId)
                                        put("transactionId", purchase.orderId ?: "")
                                        if (id != null) put("id", id)
                                    }
                                    NativeActionCoordinator.dispatchEvent(activity, pendingEvent, payload.toString())
                                } else {
                                    val payload = JSONObject().apply {
                                        put("productId", productId)
                                        put("purchase", purchaseToJson(purchase, productId))
                                        put("isSandbox", false)
                                        put("signedPayload", purchase.originalJson)
                                        if (id != null) put("id", id)
                                    }
                                    NativeActionCoordinator.dispatchEvent(activity, eventClass, payload.toString())
                                }
                            }
                        }
                        BillingClient.BillingResponseCode.USER_CANCELED -> {
                            val cancelEvent = "Native\\Mobile\\Iap\\Events\\PurchaseCancelled"
                            val payload = JSONObject().apply {
                                put("productId", productId); if (id != null) put("id", id)
                            }
                            NativeActionCoordinator.dispatchEvent(activity, cancelEvent, payload.toString())
                        }
                        BillingClient.BillingResponseCode.ITEM_ALREADY_OWNED -> {
                            manager.queryPurchases { allPurchases ->
                                val existing = allPurchases.firstOrNull { productId in it.products }
                                if (existing != null) {
                                    val payload = JSONObject().apply {
                                        put("productId", productId)
                                        put("purchase", purchaseToJson(existing, productId))
                                        put("isSandbox", false)
                                        put("signedPayload", existing.originalJson)
                                        if (id != null) put("id", id)
                                    }
                                    NativeActionCoordinator.dispatchEvent(activity, eventClass, payload.toString())
                                }
                            }
                        }
                        else -> {
                            val failedEvent = "Native\\Mobile\\Iap\\Events\\PurchaseFailed"
                            val payload = JSONObject().apply {
                                put("productId", productId)
                                put("code", billingResponseCodeToString(billingResult.responseCode))
                                put("message", billingResult.debugMessage ?: "Purchase failed")
                                if (id != null) put("id", id)
                            }
                            NativeActionCoordinator.dispatchEvent(activity, failedEvent, payload.toString())
                        }
                    }
                }
            }
        }
    }

    class CompleteTransaction(private val activity: FragmentActivity) : BridgeFunction {
        @Suppress("UNCHECKED_CAST")
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val purchase = parameters["purchase"] as? Map<String, Any>
                ?: throw BridgeError.InvalidParameters("purchase dictionary is required")
            val productId = purchase["productId"] as? String
                ?: throw BridgeError.InvalidParameters("purchase.productId is required")
            val purchaseToken = purchase["purchaseToken"] as? String
                ?: throw BridgeError.InvalidParameters("purchase.purchaseToken is required")
            val productType = parameters["type"] as? String

            val manager = IapBillingManager.getInstance(activity)
            var success = false
            var code = "unknown_error"
            var message = "Transaction completion failed"
            val latch = java.util.concurrent.CountDownLatch(1)

            manager.completePurchase(productId, productType, purchaseToken) { billingResult ->
                success = billingResult.responseCode == BillingClient.BillingResponseCode.OK
                code = billingResponseCodeToString(billingResult.responseCode)
                message = billingResult.debugMessage ?: message
                latch.countDown()
            }

            latch.await(5, java.util.concurrent.TimeUnit.SECONDS)

            return if (success) {
                mapOf("status" to "success")
            } else {
                mapOf("status" to "failed", "code" to code, "message" to message)
            }
        }
    }

    class Restore(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
            val eventClass = parameters["event"] as? String
                ?: "Native\\Mobile\\Iap\\Events\\RestoreCompleted"
            val manager = IapBillingManager.getInstance(activity)
            Handler(Looper.getMainLooper()).post {
                manager.queryPurchases { purchases ->
                    val purchasesArray = JSONArray()
                    for (purchase in purchases) {
                        if (purchase.purchaseState == com.android.billingclient.api.Purchase.PurchaseState.PURCHASED) {
                            for (pid in purchase.products) purchasesArray.put(purchaseToJson(purchase, pid))
                        }
                    }
                    val payload = JSONObject().apply {
                        put("purchases", purchasesArray); if (id != null) put("id", id)
                    }
                    NativeActionCoordinator.dispatchEvent(activity, eventClass, payload.toString())
                }
            }
            return mapOf("status" to "success")
        }
    }

    class GetEntitlements(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val manager = IapBillingManager.getInstance(activity)
            val entitlements = mutableListOf<Map<String, Any?>>()
            val latch = java.util.concurrent.CountDownLatch(1)
            manager.queryPurchases { purchases ->
                for (purchase in purchases) {
                    if (purchase.purchaseState == com.android.billingclient.api.Purchase.PurchaseState.PURCHASED) {
                        for (productId in purchase.products) {
                            entitlements.add(mapOf(
                                "productId" to productId, "isActive" to true,
                                "transactionId" to purchase.orderId,
                                "originalTransactionId" to purchase.orderId,
                                "expiresAt" to null
                            ))
                        }
                    }
                }
                latch.countDown()
            }
            latch.await(5, java.util.concurrent.TimeUnit.SECONDS)
            val result = entitlements.map { e ->
                val map = mutableMapOf<String, Any>()
                for ((key, value) in e) { if (value != null) map[key] = value }
                map
            }
            return mapOf("entitlements" to result)
        }
    }

    class InitTransactionObserver(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "InitTransactionObserver called (no-op on Android)")
            return emptyMap()
        }
    }

    // --- Helpers ---

    private fun productDetailsToJson(details: ProductDetails, manager: IapBillingManager): JSONObject {
        val registeredType = manager.registeredProducts[details.productId]?.get("type") as? String
        val typeString = when {
            registeredType == "subscription" -> "subscription"
            registeredType == "consumable" -> "consumable"
            registeredType != null -> registeredType
            details.productType == BillingClient.ProductType.SUBS -> "subscription"
            else -> "non_consumable"
        }
        val json = JSONObject().apply {
            put("id", details.productId); put("title", details.title)
            put("description", details.description)
        }
        if (details.productType == BillingClient.ProductType.SUBS) {
            val pricingPhase = details.subscriptionOfferDetails?.firstOrNull()
                ?.pricingPhases?.pricingPhaseList?.firstOrNull()
            if (pricingPhase != null) {
                json.put("price", pricingPhase.formattedPrice)
                json.put("priceAmount", pricingPhase.priceAmountMicros / 1_000_000.0)
                json.put("currency", pricingPhase.priceCurrencyCode)
                json.put("subscriptionPeriod", pricingPhase.billingPeriod)
            } else {
                json.put("price", ""); json.put("priceAmount", 0.0)
                json.put("currency", "USD"); json.put("subscriptionPeriod", JSONObject.NULL)
            }
        } else {
            val oneTimeOffer = details.oneTimePurchaseOfferDetails
            if (oneTimeOffer != null) {
                json.put("price", oneTimeOffer.formattedPrice)
                json.put("priceAmount", oneTimeOffer.priceAmountMicros / 1_000_000.0)
                json.put("currency", oneTimeOffer.priceCurrencyCode)
            } else {
                json.put("price", ""); json.put("priceAmount", 0.0); json.put("currency", "USD")
            }
            json.put("subscriptionPeriod", JSONObject.NULL)
        }
        json.put("type", typeString)
        return json
    }

    private fun purchaseToJson(purchase: com.android.billingclient.api.Purchase, productId: String): JSONObject {
        val dateFormat = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", Locale.US).apply {
            timeZone = TimeZone.getTimeZone("UTC")
        }
        val state = when (purchase.purchaseState) {
            com.android.billingclient.api.Purchase.PurchaseState.PURCHASED -> "completed"
            com.android.billingclient.api.Purchase.PurchaseState.PENDING -> "pending"
            else -> "failed"
        }
        return JSONObject().apply {
            put("productId", productId)
            put("transactionId", purchase.orderId ?: "")
            put("originalTransactionId", purchase.orderId ?: "")
            put("state", state)
            put("purchaseDate", dateFormat.format(Date(purchase.purchaseTime)))
            put("expiresAt", JSONObject.NULL)
            put("isSandbox", false)
            put("signedPayload", purchase.originalJson)
            put("purchaseToken", purchase.purchaseToken)
            put("signature", purchase.signature)
            put("quantity", purchase.quantity)
        }
    }

    private fun billingResponseCodeToString(code: Int): String = when (code) {
        BillingClient.BillingResponseCode.USER_CANCELED -> "user_cancelled"
        BillingClient.BillingResponseCode.SERVICE_UNAVAILABLE -> "service_unavailable"
        BillingClient.BillingResponseCode.BILLING_UNAVAILABLE -> "billing_unavailable"
        BillingClient.BillingResponseCode.ITEM_UNAVAILABLE -> "item_unavailable"
        BillingClient.BillingResponseCode.DEVELOPER_ERROR -> "developer_error"
        BillingClient.BillingResponseCode.FEATURE_NOT_SUPPORTED -> "feature_not_supported"
        BillingClient.BillingResponseCode.ITEM_ALREADY_OWNED -> "item_already_owned"
        BillingClient.BillingResponseCode.ITEM_NOT_OWNED -> "item_not_owned"
        else -> "unknown_error"
    }
}
