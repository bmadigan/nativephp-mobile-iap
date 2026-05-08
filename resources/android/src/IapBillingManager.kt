package com.bmadigan.plugins.iap

import android.app.Activity
import android.content.Context
import android.util.Log
import com.android.billingclient.api.*
import kotlinx.coroutines.*

class IapBillingManager private constructor(private val context: Context) : PurchasesUpdatedListener {

    companion object {
        private const val TAG = "IapBillingManager"
        @Volatile private var instance: IapBillingManager? = null
        fun getInstance(context: Context): IapBillingManager {
            return instance ?: synchronized(this) {
                instance ?: IapBillingManager(context.applicationContext).also { instance = it }
            }
        }
    }

    private var billingClient: BillingClient? = null
    var registeredProducts: Map<String, Map<String, Any>> = emptyMap()
        private set
    private val cachedProductDetails = mutableMapOf<String, ProductDetails>()
    private var pendingPurchaseCallback: ((BillingResult, List<Purchase>?) -> Unit)? = null

    fun ensureConnected(onReady: (Boolean) -> Unit) {
        val client = billingClient
        if (client != null && client.isReady) {
            onReady(true)
            return
        }
        billingClient = BillingClient.newBuilder(context)
            .setListener(this)
            .enablePendingPurchases()
            .build()
        billingClient?.startConnection(object : BillingClientStateListener {
            override fun onBillingSetupFinished(billingResult: BillingResult) {
                if (billingResult.responseCode == BillingClient.BillingResponseCode.OK) {
                    Log.d(TAG, "BillingClient connected")
                    onReady(true)
                } else {
                    Log.e(TAG, "BillingClient setup failed: ${billingResult.debugMessage}")
                    onReady(false)
                }
            }
            override fun onBillingServiceDisconnected() {
                Log.w(TAG, "BillingClient disconnected")
            }
        })
    }

    fun canMakePayments(): Boolean = billingClient?.isReady == true

    fun registerProducts(products: Map<String, Map<String, Any>>) {
        registeredProducts = products
    }

    fun queryProducts(
        productIds: List<String>,
        onResult: (List<ProductDetails>, List<String>) -> Unit
    ) {
        ensureConnected { ready ->
            if (!ready) { onResult(emptyList(), productIds); return@ensureConnected }
            val productList = productIds.map { productId ->
                QueryProductDetailsParams.Product.newBuilder()
                    .setProductId(productId)
                    .setProductType(getProductType(productId))
                    .build()
            }
            val params = QueryProductDetailsParams.newBuilder().setProductList(productList).build()
            billingClient?.queryProductDetailsAsync(params) { billingResult, productDetailsList ->
                if (billingResult.responseCode == BillingClient.BillingResponseCode.OK) {
                    for (details in productDetailsList) {
                        cachedProductDetails[details.productId] = details
                    }
                    val foundIds = productDetailsList.map { it.productId }.toSet()
                    val invalidIds = productIds.filter { it !in foundIds }
                    onResult(productDetailsList, invalidIds)
                } else {
                    onResult(emptyList(), productIds)
                }
            }
        }
    }

    fun purchase(
        activity: Activity, productId: String,
        onResult: (BillingResult, List<Purchase>?) -> Unit
    ) {
        val productDetails = cachedProductDetails[productId]
        if (productDetails == null) {
            val result = BillingResult.newBuilder()
                .setResponseCode(BillingClient.BillingResponseCode.ITEM_UNAVAILABLE)
                .setDebugMessage("Product not found. Call GetProducts first.").build()
            onResult(result, null); return
        }
        ensureConnected { ready ->
            if (!ready) {
                val result = BillingResult.newBuilder()
                    .setResponseCode(BillingClient.BillingResponseCode.SERVICE_UNAVAILABLE)
                    .setDebugMessage("Billing service not available").build()
                onResult(result, null); return@ensureConnected
            }
            pendingPurchaseCallback = onResult
            val flowParamsBuilder = BillingFlowParams.newBuilder()
            val isSubscription = getProductType(productId) == BillingClient.ProductType.SUBS
            if (isSubscription && productDetails.subscriptionOfferDetails != null) {
                val offerToken = productDetails.subscriptionOfferDetails!!.first().offerToken
                flowParamsBuilder.setProductDetailsParamsList(listOf(
                    BillingFlowParams.ProductDetailsParams.newBuilder()
                        .setProductDetails(productDetails).setOfferToken(offerToken).build()
                ))
            } else {
                flowParamsBuilder.setProductDetailsParamsList(listOf(
                    BillingFlowParams.ProductDetailsParams.newBuilder()
                        .setProductDetails(productDetails).build()
                ))
            }
            val billingResult = billingClient?.launchBillingFlow(activity, flowParamsBuilder.build())
            if (billingResult?.responseCode != BillingClient.BillingResponseCode.OK) {
                pendingPurchaseCallback = null
                if (billingResult != null) onResult(billingResult, null)
            }
        }
    }

    fun queryPurchases(onResult: (List<Purchase>) -> Unit) {
        ensureConnected { ready ->
            if (!ready) { onResult(emptyList()); return@ensureConnected }
            val allPurchases = mutableListOf<Purchase>()
            var queriesRemaining = 2
            fun checkComplete() { queriesRemaining--; if (queriesRemaining <= 0) onResult(allPurchases) }
            billingClient?.queryPurchasesAsync(
                QueryPurchasesParams.newBuilder().setProductType(BillingClient.ProductType.INAPP).build()
            ) { billingResult, purchases ->
                if (billingResult.responseCode == BillingClient.BillingResponseCode.OK) allPurchases.addAll(purchases)
                checkComplete()
            }
            billingClient?.queryPurchasesAsync(
                QueryPurchasesParams.newBuilder().setProductType(BillingClient.ProductType.SUBS).build()
            ) { billingResult, purchases ->
                if (billingResult.responseCode == BillingClient.BillingResponseCode.OK) allPurchases.addAll(purchases)
                checkComplete()
            }
        }
    }

    fun acknowledgePurchase(purchaseToken: String) {
        val params = AcknowledgePurchaseParams.newBuilder().setPurchaseToken(purchaseToken).build()
        billingClient?.acknowledgePurchase(params) { billingResult ->
            if (billingResult.responseCode == BillingClient.BillingResponseCode.OK) {
                Log.d(TAG, "Purchase acknowledged successfully")
            } else {
                Log.e(TAG, "Failed to acknowledge purchase: ${billingResult.debugMessage}")
            }
        }
    }

    fun completePurchase(
        productId: String,
        productType: String?,
        purchaseToken: String,
        onResult: (BillingResult) -> Unit
    ) {
        ensureConnected { ready ->
            if (!ready) {
                onResult(
                    BillingResult.newBuilder()
                        .setResponseCode(BillingClient.BillingResponseCode.SERVICE_UNAVAILABLE)
                        .setDebugMessage("Billing service not available")
                        .build()
                )
                return@ensureConnected
            }

            val registeredType = registeredProducts[productId]?.get("type") as? String
            if ((productType ?: registeredType) == "consumable") {
                val params = ConsumeParams.newBuilder().setPurchaseToken(purchaseToken).build()
                billingClient?.consumeAsync(params) { billingResult, _ -> onResult(billingResult) }
            } else {
                val params = AcknowledgePurchaseParams.newBuilder().setPurchaseToken(purchaseToken).build()
                billingClient?.acknowledgePurchase(params) { billingResult -> onResult(billingResult) }
            }
        }
    }

    override fun onPurchasesUpdated(billingResult: BillingResult, purchases: List<Purchase>?) {
        pendingPurchaseCallback?.invoke(billingResult, purchases)
        pendingPurchaseCallback = null
    }

    private fun getProductType(productId: String): String {
        val type = registeredProducts[productId]?.get("type") as? String ?: "non_consumable"
        return if (type == "subscription") BillingClient.ProductType.SUBS else BillingClient.ProductType.INAPP
    }

    fun getCachedProduct(productId: String): ProductDetails? = cachedProductDetails[productId]

    fun disconnect() { billingClient?.endConnection(); billingClient = null }
}
