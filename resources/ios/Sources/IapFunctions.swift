import Foundation
import StoreKit

// MARK: - IAP Function Namespace

enum IapFunctions {

    /// Registered products config from PHP (stored in memory for type mapping)
    private static var registeredProducts: [String: [String: Any]] = [:]

    /// Cached StoreKit Product objects for purchase flow
    private static var cachedProducts: [String: Product] = [:]

    // MARK: - Iap.CanMakePayments

    class CanMakePayments: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            print("💰 Iap.CanMakePayments called")
            let canMake = AppStore.canMakePayments
            print(canMake ? "✅ Device can make payments" : "⚠️ Device cannot make payments")
            return ["canMakePayments": canMake]
        }
    }

    // MARK: - Iap.RegisterProducts

    class RegisterProducts: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let products = parameters["products"] as? [String: [String: Any]] else {
                throw BridgeError.invalidParameters("products dictionary is required")
            }
            print("💰 Iap.RegisterProducts called with \(products.count) products")
            IapFunctions.registeredProducts = products
            for (id, config) in products {
                let type = config["type"] as? String ?? "non_consumable"
                print("   📦 \(id) (\(type))")
            }
            return [:]
        }
    }

    // MARK: - Iap.GetProducts

    class GetProducts: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let productIds = parameters["productIds"] as? [String], !productIds.isEmpty else {
                throw BridgeError.invalidParameters("productIds array is required and must not be empty")
            }
            let id = parameters["id"] as? String
            let eventClass = parameters["event"] as? String
                ?? "Native\\Mobile\\Iap\\Events\\ProductsLoaded"

            print("💰 Iap.GetProducts called for: \(productIds.joined(separator: ", "))")

            DispatchQueue.main.async {
                Task {
                    do {
                        let storeProducts = try await Product.products(for: Set(productIds))
                        for product in storeProducts {
                            IapFunctions.cachedProducts[product.id] = product
                        }
                        let productsArray: [[String: Any]] = storeProducts.map { product in
                            return IapFunctions.productToDict(product)
                        }
                        let foundIds = Set(storeProducts.map { $0.id })
                        let invalidIds = productIds.filter { !foundIds.contains($0) }

                        var payload: [String: Any] = [
                            "products": productsArray,
                            "invalidIds": invalidIds
                        ]
                        if let id = id { payload["id"] = id }

                        print("✅ Loaded \(storeProducts.count) products, \(invalidIds.count) invalid")
                        LaravelBridge.shared.send?(eventClass, payload)
                    } catch {
                        print("❌ Failed to load products: \(error.localizedDescription)")
                        var payload: [String: Any] = [
                            "products": [] as [[String: Any]],
                            "invalidIds": productIds
                        ]
                        if let id = id { payload["id"] = id }
                        LaravelBridge.shared.send?(eventClass, payload)
                    }
                }
            }
            return ["status": "success"]
        }
    }

    // MARK: - Iap.Purchase

    class Purchase: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let productId = parameters["productId"] as? String else {
                throw BridgeError.invalidParameters("productId is required")
            }
            let id = parameters["id"] as? String
            let eventClass = parameters["event"] as? String
                ?? "Native\\Mobile\\Iap\\Events\\PurchaseCompleted"

            print("💰 Iap.Purchase called for: \(productId)")

            DispatchQueue.main.async {
                Task {
                    var product = IapFunctions.cachedProducts[productId]
                    if product == nil {
                        print("⚠️ Product not cached, fetching from App Store...")
                        do {
                            let products = try await Product.products(for: [productId])
                            product = products.first
                            if let product = product {
                                IapFunctions.cachedProducts[productId] = product
                            }
                        } catch {
                            print("❌ Failed to fetch product: \(error.localizedDescription)")
                            IapFunctions.dispatchPurchaseFailed(
                                productId: productId, code: "item_unavailable",
                                message: "Could not load product: \(error.localizedDescription)", id: id)
                            return
                        }
                    }
                    guard let product = product else {
                        print("❌ Product not found: \(productId)")
                        IapFunctions.dispatchPurchaseFailed(
                            productId: productId, code: "item_unavailable",
                            message: "Product not found in App Store", id: id)
                        return
                    }
                    do {
                        let result = try await product.purchase()
                        switch result {
                        case .success(let verification):
                            let transaction = try IapFunctions.checkVerification(verification)
                            await transaction.finish()
                            let purchaseDict = IapFunctions.transactionToDict(
                                transaction, productId: productId)
                            let isSandbox = transaction.environment == .sandbox
                            var payload: [String: Any] = [
                                "productId": productId,
                                "purchase": purchaseDict,
                                "isSandbox": isSandbox
                            ]
                            if let id = id { payload["id"] = id }
                            payload["signedPayload"] = verification.jwsRepresentation
                            print("✅ Purchase completed for \(productId)")
                            LaravelBridge.shared.send?(eventClass, payload)

                        case .userCancelled:
                            print("⚠️ Purchase cancelled by user for \(productId)")
                            let cancelEvent = "Native\\Mobile\\Iap\\Events\\PurchaseCancelled"
                            var payload: [String: Any] = ["productId": productId]
                            if let id = id { payload["id"] = id }
                            LaravelBridge.shared.send?(cancelEvent, payload)

                        case .pending:
                            print("⏳ Purchase pending for \(productId)")
                            let pendingEvent = "Native\\Mobile\\Iap\\Events\\PurchasePending"
                            var payload: [String: Any] = ["productId": productId]
                            if let id = id { payload["id"] = id }
                            LaravelBridge.shared.send?(pendingEvent, payload)

                        @unknown default:
                            print("❌ Unknown purchase result for \(productId)")
                            IapFunctions.dispatchPurchaseFailed(
                                productId: productId, code: "unknown",
                                message: "Unknown purchase result", id: id)
                        }
                    } catch StoreKit.StoreKitError.userCancelled {
                        print("⚠️ Purchase cancelled (StoreKitError) for \(productId)")
                        let cancelEvent = "Native\\Mobile\\Iap\\Events\\PurchaseCancelled"
                        var payload: [String: Any] = ["productId": productId]
                        if let id = id { payload["id"] = id }
                        LaravelBridge.shared.send?(cancelEvent, payload)
                    } catch {
                        print("❌ Purchase failed for \(productId): \(error.localizedDescription)")
                        IapFunctions.dispatchPurchaseFailed(
                            productId: productId, code: "purchase_failed",
                            message: error.localizedDescription, id: id)
                    }
                }
            }
            return ["status": "success"]
        }
    }

    // MARK: - Iap.Restore

    class Restore: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let id = parameters["id"] as? String
            let eventClass = parameters["event"] as? String
                ?? "Native\\Mobile\\Iap\\Events\\RestoreCompleted"
            print("💰 Iap.Restore called")

            DispatchQueue.main.async {
                Task {
                    do {
                        try await AppStore.sync()
                        var purchases: [[String: Any]] = []
                        for await verification in Transaction.currentEntitlements {
                            if let transaction = try? IapFunctions.checkVerification(verification) {
                                let purchaseDict = IapFunctions.transactionToDict(
                                    transaction, productId: transaction.productID)
                                purchases.append(purchaseDict)
                            }
                        }
                        var payload: [String: Any] = ["purchases": purchases]
                        if let id = id { payload["id"] = id }
                        print("✅ Restore completed with \(purchases.count) purchases")
                        LaravelBridge.shared.send?(eventClass, payload)
                    } catch {
                        print("❌ Restore failed: \(error.localizedDescription)")
                        var payload: [String: Any] = ["purchases": [] as [[String: Any]]]
                        if let id = id { payload["id"] = id }
                        LaravelBridge.shared.send?(eventClass, payload)
                    }
                }
            }
            return ["status": "success"]
        }
    }

    // MARK: - Iap.GetEntitlements

    class GetEntitlements: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            print("💰 Iap.GetEntitlements called")
            var entitlements: [[String: Any]] = []
            let semaphore = DispatchSemaphore(value: 0)

            Task {
                for await verification in Transaction.currentEntitlements {
                    if let transaction = try? IapFunctions.checkVerification(verification) {
                        let entitlement: [String: Any] = [
                            "productId": transaction.productID,
                            "isActive": true,
                            "transactionId": String(transaction.id),
                            "originalTransactionId": String(transaction.originalID),
                            "expiresAt": transaction.expirationDate.map {
                                ISO8601DateFormatter().string(from: $0)
                            } as Any
                        ]
                        entitlements.append(entitlement)
                    }
                }
                semaphore.signal()
            }

            let result = semaphore.wait(timeout: .now() + 5)
            if result == .timedOut { print("⚠️ Entitlements fetch timed out") }
            print("✅ Found \(entitlements.count) active entitlements")
            return ["entitlements": entitlements]
        }
    }

    // MARK: - Iap.InitTransactionObserver

    class InitTransactionObserver: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            print("💰 Iap.InitTransactionObserver called")
            IapTransactionObserver.shared.start()
            print("✅ Transaction observer started")
            return [:]
        }
    }

    // MARK: - Helper Methods

    private static func checkVerification<T>(_ result: VerificationResult<T>) throws -> T {
        switch result {
        case .unverified(_, let error): throw error
        case .verified(let value): return value
        }
    }

    private static func productToDict(_ product: Product) -> [String: Any] {
        let typeString: String
        switch product.type {
        case .consumable: typeString = "consumable"
        case .nonConsumable: typeString = "non_consumable"
        case .autoRenewable: typeString = "subscription"
        case .nonRenewable: typeString = "subscription"
        default:
            typeString = (registeredProducts[product.id]?["type"] as? String) ?? "non_consumable"
        }
        var subscriptionPeriod: Any = NSNull()
        if let subscription = product.subscription {
            let unit = subscription.subscriptionPeriod.unit
            let value = subscription.subscriptionPeriod.value
            switch unit {
            case .day: subscriptionPeriod = "P\(value)D"
            case .week: subscriptionPeriod = "P\(value)W"
            case .month: subscriptionPeriod = "P\(value)M"
            case .year: subscriptionPeriod = "P\(value)Y"
            @unknown default: subscriptionPeriod = NSNull()
            }
        }
        return [
            "id": product.id, "title": product.displayName,
            "description": product.description, "price": product.displayPrice,
            "priceAmount": NSDecimalNumber(decimal: product.price).doubleValue,
            "currency": product.priceFormatStyle.currencyCode ?? "USD",
            "type": typeString, "subscriptionPeriod": subscriptionPeriod
        ]
    }

    private static func transactionToDict(_ transaction: Transaction, productId: String) -> [String: Any] {
        let dateFormatter = ISO8601DateFormatter()
        var dict: [String: Any] = [
            "productId": productId,
            "transactionId": String(transaction.id),
            "originalTransactionId": String(transaction.originalID),
            "state": "completed",
            "purchaseDate": dateFormatter.string(from: transaction.purchaseDate),
            "isSandbox": transaction.environment == .sandbox
        ]
        if let expirationDate = transaction.expirationDate {
            dict["expiresAt"] = dateFormatter.string(from: expirationDate)
        } else {
            dict["expiresAt"] = NSNull()
        }
        return dict
    }

    private static func dispatchPurchaseFailed(
        productId: String, code: String, message: String, id: String?
    ) {
        let failedEvent = "Native\\Mobile\\Iap\\Events\\PurchaseFailed"
        var payload: [String: Any] = [
            "productId": productId, "code": code, "message": message
        ]
        if let id = id { payload["id"] = id }
        LaravelBridge.shared.send?(failedEvent, payload)
    }
}

// MARK: - Transaction Observer

class IapTransactionObserver {
    static let shared = IapTransactionObserver()
    private var updateTask: Task<Void, Never>?
    private init() {}

    func start() {
        updateTask = Task(priority: .background) {
            for await verification in Transaction.updates {
                do {
                    let transaction = try checkVerification(verification)
                    await transaction.finish()
                    print("💰 Transaction update received for: \(transaction.productID)")

                    var entitlements: [[String: Any]] = []
                    for await entVerification in Transaction.currentEntitlements {
                        if let entTransaction = try? checkVerification(entVerification) {
                            let entitlement: [String: Any] = [
                                "productId": entTransaction.productID,
                                "isActive": true,
                                "transactionId": String(entTransaction.id),
                                "originalTransactionId": String(entTransaction.originalID),
                                "expiresAt": entTransaction.expirationDate.map {
                                    ISO8601DateFormatter().string(from: $0)
                                } as Any
                            ]
                            entitlements.append(entitlement)
                        }
                    }

                    let eventClass = "Native\\Mobile\\Iap\\Events\\EntitlementsUpdated"
                    let payload: [String: Any] = ["entitlements": entitlements]
                    DispatchQueue.main.async {
                        LaravelBridge.shared.send?(eventClass, payload)
                    }
                } catch {
                    print("❌ Transaction update verification failed: \(error.localizedDescription)")
                }
            }
        }
    }

    func stop() { updateTask?.cancel(); updateTask = nil }

    private func checkVerification<T>(_ result: VerificationResult<T>) throws -> T {
        switch result {
        case .unverified(_, let error): throw error
        case .verified(let value): return value
        }
    }
}
