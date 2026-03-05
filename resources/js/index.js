const baseUrl = '/_native/api/call';

async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ method, params }),
    });
    return response.json();
}

export async function canMakePayments() {
    return bridgeCall('Iap.CanMakePayments');
}

export async function registerProducts(products) {
    return bridgeCall('Iap.RegisterProducts', { products });
}

export async function getProducts(productIds, options = {}) {
    return bridgeCall('Iap.GetProducts', { productIds, ...options });
}

export async function purchase(productId, options = {}) {
    return bridgeCall('Iap.Purchase', { productId, ...options });
}

export async function restore(options = {}) {
    return bridgeCall('Iap.Restore', options);
}

export async function getEntitlements() {
    return bridgeCall('Iap.GetEntitlements');
}

export const Events = {
    ProductsLoaded: 'Native\\Mobile\\Iap\\Events\\ProductsLoaded',
    PurchaseCompleted: 'Native\\Mobile\\Iap\\Events\\PurchaseCompleted',
    PurchaseFailed: 'Native\\Mobile\\Iap\\Events\\PurchaseFailed',
    PurchaseCancelled: 'Native\\Mobile\\Iap\\Events\\PurchaseCancelled',
    PurchasePending: 'Native\\Mobile\\Iap\\Events\\PurchasePending',
    RestoreCompleted: 'Native\\Mobile\\Iap\\Events\\RestoreCompleted',
    EntitlementsUpdated: 'Native\\Mobile\\Iap\\Events\\EntitlementsUpdated',
};
