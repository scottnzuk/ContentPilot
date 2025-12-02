/**
 * Service Worker for AI Auto News Poster Dashboard PWA
 * 
 * Provides offline functionality, caching strategies, and background sync
 * for the dashboard to work seamlessly without internet connection.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

const CACHE_NAME = 'aanp-dashboard-v2.0.0';
const OFFLINE_URL = '/wp-admin/admin.php?page=ai-news-dashboard&offline=true';

// Assets to cache for offline use
const STATIC_ASSETS = [
    '/',
    '/wp-admin/admin.php?page=ai-news-dashboard',
    '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/css/dashboard.css',
    '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/js/dashboard.js',
    '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/manifest.json',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// API endpoints to cache
const API_ENDPOINTS = [
    '/wp-json/ai-auto-news/v1/',
    '/wp-admin/admin-ajax.php?action=ai_news_get_dashboard_data',
    '/wp-admin/admin-ajax.php?action=aanp_get_performance_metrics',
    '/wp-admin/admin-ajax.php?action=aanp_get_realtime_metrics'
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('[SW] Install event');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[SW] Static assets cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('[SW] Failed to cache static assets:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activate event');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(cacheName => cacheName !== CACHE_NAME)
                        .map(cacheName => {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Old caches cleaned up');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache with network fallback
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Handle different types of requests
    if (isStaticAsset(url)) {
        // Static assets - cache first strategy
        event.respondWith(cacheFirstStrategy(request));
    } else if (isAPIEndpoint(url)) {
        // API endpoints - network first with cache fallback
        event.respondWith(networkFirstStrategy(request));
    } else if (isNavigationRequest(request)) {
        // Navigation requests - offline fallback
        event.respondWith(handleNavigationRequest(request));
    } else {
        // Other requests - network first
        event.respondWith(networkFirstStrategy(request));
    }
});

// Cache first strategy for static assets
async function cacheFirstStrategy(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            console.log('[SW] Serving from cache:', request.url);
            return cachedResponse;
        }
        
        console.log('[SW] Fetching from network:', request.url);
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('[SW] Cache first strategy failed:', error);
        throw error;
    }
}

// Network first strategy for API calls
async function networkFirstStrategy(request) {
    try {
        console.log('[SW] Network first for:', request.url);
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            console.log('[SW] Serving from cache fallback:', request.url);
            return cachedResponse;
        }
        
        // If no cache available, return offline response
        if (isAPIRequest(request)) {
            return new Response(
                JSON.stringify({
                    error: 'Offline',
                    message: 'This feature is not available offline',
                    cached: false
                }),
                {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                }
            );
        }
        
        throw error;
    }
}

// Handle navigation requests with offline fallback
async function handleNavigationRequest(request) {
    try {
        console.log('[SW] Handling navigation request:', request.url);
        const networkResponse = await fetch(request);
        return networkResponse;
    } catch (error) {
        console.log('[SW] Navigation failed, serving offline page');
        const cache = await caches.open(CACHE_NAME);
        const offlineResponse = await cache.match(OFFLINE_URL);
        
        if (offlineResponse) {
            return offlineResponse;
        }
        
        // Fallback to cached index
        return cache.match('/') || new Response('Offline', { status: 503 });
    }
}

// Background sync for when connection is restored
self.addEventListener('sync', event => {
    console.log('[SW] Background sync:', event.tag);
    
    if (event.tag === 'background-sync') {
        event.waitUntil(performBackgroundSync());
    }
});

// Perform background synchronization
async function performBackgroundSync() {
    try {
        console.log('[SW] Performing background sync');
        
        // Sync cached data to server
        await syncCachedData();
        
        // Update cache with latest data
        await updateCacheWithFreshData();
        
        console.log('[SW] Background sync completed');
    } catch (error) {
        console.error('[SW] Background sync failed:', error);
    }
}

// Sync cached data to server when online
async function syncCachedData() {
    const cache = await caches.open(CACHE_NAME);
    const requests = await cache.keys();
    
    for (const request of requests) {
        if (isAPIRequest(request)) {
            try {
                const cachedResponse = await cache.match(request);
                if (cachedResponse && shouldSyncRequest(request)) {
                    // Attempt to sync cached data
                    const networkResponse = await fetch(request);
                    if (networkResponse.ok) {
                        console.log('[SW] Synced cached request:', request.url);
                    }
                }
            } catch (error) {
                console.log('[SW] Failed to sync request:', request.url);
            }
        }
    }
}

// Update cache with fresh data from server
async function updateCacheWithFreshData() {
    const cache = await caches.open(CACHE_NAME);
    
    for (const endpoint of API_ENDPOINTS) {
        try {
            const response = await fetch(endpoint);
            if (response.ok) {
                await cache.put(endpoint, response.clone());
                console.log('[SW] Updated cache for:', endpoint);
            }
        } catch (error) {
            console.log('[SW] Failed to update cache for:', endpoint);
        }
    }
}

// Check if URL is a static asset
function isStaticAsset(url) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2'];
    const staticPaths = ['/assets/', '/fonts/', '/icons/'];
    
    return staticExtensions.some(ext => url.pathname.endsWith(ext)) ||
           staticPaths.some(path => url.pathname.includes(path)) ||
           url.hostname.includes('fonts.googleapis.com') ||
           url.hostname.includes('cdnjs.cloudflare.com') ||
           url.hostname.includes('cdn.jsdelivr.net');
}

// Check if URL is an API endpoint
function isAPIEndpoint(url) {
    return url.pathname.includes('/wp-json/') ||
           url.pathname.includes('/admin-ajax.php');
}

// Check if request is a navigation request
function isNavigationRequest(request) {
    return request.mode === 'navigate' ||
           (request.method === 'GET' && request.headers.get('accept').includes('text/html'));
}

// Check if request is an API request
function isAPIRequest(request) {
    const url = new URL(request.url);
    return isAPIEndpoint(url);
}

// Check if request should be synced
function shouldSyncRequest(request) {
    const url = new URL(request.url);
    const syncEndpoints = ['/wp-admin/admin-ajax.php'];
    
    return syncEndpoints.some(endpoint => url.pathname.includes(endpoint)) &&
           request.method === 'POST';
}

// Push notification handler
self.addEventListener('push', event => {
    console.log('[SW] Push notification received');
    
    const options = {
        body: 'New dashboard update available',
        icon: '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/icons/icon-192x192.png',
        badge: '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/icons/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View Dashboard',
                icon: '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/icons/checkmark.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/icons/xmark.png'
            }
        ]
    };
    
    if (event.data) {
        const payload = event.data.json();
        options.body = payload.body || options.body;
        options.data = { ...options.data, ...payload.data };
    }
    
    event.waitUntil(
        self.registration.showNotification('ContentPilot', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification click received');
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/wp-admin/admin.php?page=ai-news-dashboard')
        );
    }
});

// Message handler for communication with main thread
self.addEventListener('message', event => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data && event.data.type) {
        switch (event.data.type) {
            case 'SKIP_WAITING':
                self.skipWaiting();
                break;
            case 'CACHE_URLS':
                cacheUrls(event.data.urls);
                break;
            case 'CLEAR_CACHE':
                clearCache(event.data.cacheName);
                break;
            case 'GET_CACHE_SIZE':
                getCacheSize().then(size => {
                    event.ports[0].postMessage({ type: 'CACHE_SIZE', size });
                });
                break;
        }
    }
});

// Cache specific URLs
async function cacheUrls(urls) {
    try {
        const cache = await caches.open(CACHE_NAME);
        await cache.addAll(urls);
        console.log('[SW] URLs cached successfully');
    } catch (error) {
        console.error('[SW] Failed to cache URLs:', error);
    }
}

// Clear specific cache
async function clearCache(cacheName) {
    try {
        await caches.delete(cacheName);
        console.log('[SW] Cache cleared:', cacheName);
    } catch (error) {
        console.error('[SW] Failed to clear cache:', error);
    }
}

// Get cache size
async function getCacheSize() {
    try {
        const cache = await caches.open(CACHE_NAME);
        const requests = await cache.keys();
        let totalSize = 0;
        
        for (const request of requests) {
            const response = await cache.match(request);
            if (response) {
                const blob = await response.blob();
                totalSize += blob.size;
            }
        }
        
        return totalSize;
    } catch (error) {
        console.error('[SW] Failed to get cache size:', error);
        return 0;
    }
}

console.log('[SW] Service Worker loaded successfully');