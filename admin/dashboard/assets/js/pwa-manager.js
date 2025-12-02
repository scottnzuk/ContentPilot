/**
 * PWA Registration and Management for AI Auto News Poster Dashboard
 * 
 * Handles service worker registration, offline functionality,
 * installation prompts, and PWA features for the dashboard.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

class AANP_PWA_Manager {
    
    /**
     * Service worker registration
     * @var ServiceWorkerRegistration|null
     */
    private $serviceWorkerRegistration = null;
    
    /**
     * PWA installation prompt
     * @var BeforeInstallPromptEvent|null
     */
    private $installPrompt = null;
    
    /**
     * PWA installation status
     * @var boolean
     */
    private $isInstalled = false;
    
    /**
     * Offline status
     * @var boolean
     */
    private $isOffline = false;
    
    /**
     * Cache size
     * @var number
     */
    private $cacheSize = 0;
    
    /**
     * Constructor
     */
    constructor() {
        this.init();
    }
    
    /**
     * Initialize PWA functionality
     */
    async init() {
        try {
            // Check if PWA features are supported
            if (!this.isPWASupported()) {
                console.warn('PWA features not supported in this browser');
                return;
            }
            
            // Register service worker
            await this.registerServiceWorker();
            
            // Set up event listeners
            this.setupEventListeners();
            
            // Check installation status
            await this.checkInstallationStatus();
            
            // Set up offline/online listeners
            this.setupConnectivityListeners();
            
            // Initialize cache management
            await this.initializeCacheManager();
            
            console.log('PWA Manager initialized successfully');
            
        } catch (error) {
            console.error('Failed to initialize PWA Manager:', error);
        }
    }
    
    /**
     * Check if PWA features are supported
     */
    isPWASupported() {
        return (
            'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window &&
            'caches' in window
        );
    }
    
    /**
     * Register service worker
     */
    async registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register(
                '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/js/service-worker.js',
                {
                    scope: '/'
                }
            );
            
            this.serviceWorkerRegistration = registration;
            
            console.log('Service Worker registered successfully:', registration);
            
            // Handle service worker updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdateAvailable();
                    }
                });
            });
            
            // Listen for messages from service worker
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.handleServiceWorkerMessage(event.data);
            });
            
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            throw error;
        }
    }
    
    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // PWA installation prompt
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            this.installPrompt = event;
            this.showInstallPrompt();
        });
        
        // PWA installed
        window.addEventListener('appinstalled', (event) => {
            console.log('PWA was installed');
            this.isInstalled = true;
            this.installPrompt = null;
            this.hideInstallPrompt();
            this.trackInstallation();
        });
        
        // Online status
        window.addEventListener('online', () => {
            this.isOffline = false;
            this.onConnectivityChange(true);
        });
        
        // Offline status
        window.addEventListener('offline', () => {
            this.isOffline = true;
            this.onConnectivityChange(false);
        });
        
        // Visibility change (for background sync)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.serviceWorkerRegistration) {
                this.serviceWorkerRegistration.sync.register('background-sync');
            }
        });
    }
    
    /**
     * Check installation status
     */
    async checkInstallationStatus() {
        // Check if running in standalone mode
        this.isInstalled = window.matchMedia('(display-mode: standalone)').matches ||
                          window.navigator.standalone === true;
        
        // Check if installed as PWA
        if (this.isInstalled) {
            console.log('PWA is already installed');
            this.hideInstallPrompt();
        }
    }
    
    /**
     * Show install prompt
     */
    showInstallPrompt() {
        // Create install button or banner
        this.createInstallBanner();
        
        // Add install button to dashboard
        this.addInstallButtonToDashboard();
    }
    
    /**
     * Hide install prompt
     */
    hideInstallPrompt() {
        const installBanner = document.getElementById('pwa-install-banner');
        const installButton = document.getElementById('pwa-install-button');
        
        if (installBanner) {
            installBanner.remove();
        }
        
        if (installButton) {
            installButton.remove();
        }
    }
    
    /**
     * Create install banner
     */
    createInstallBanner() {
        if (document.getElementById('pwa-install-banner')) {
            return; // Already exists
        }
        
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.innerHTML = `
            <div class="pwa-banner-content">
                <div class="pwa-banner-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="pwa-banner-text">
                    <h3>Install AI Auto News Poster</h3>
                    <p>Install the dashboard as a native app for better performance and offline access</p>
                </div>
                <div class="pwa-banner-actions">
                    <button id="pwa-install-yes" class="btn btn-primary">Install</button>
                    <button id="pwa-install-no" class="btn btn-outline">Not Now</button>
                </div>
            </div>
        `;
        
        // Add styles
        banner.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: var(--glass-white-strong);
            backdrop-filter: var(--backdrop-blur-lg);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-glass);
            z-index: 10000;
            animation: slideInUp 0.3s ease-out;
        `;
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInUp {
                from { transform: translateY(100%); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .pwa-banner-content {
                display: flex;
                align-items: center;
                padding: 1rem;
                gap: 1rem;
            }
            .pwa-banner-icon {
                width: 48px;
                height: 48px;
                background: var(--gradient-primary);
                border-radius: var(--border-radius-lg);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.25rem;
            }
            .pwa-banner-text {
                flex: 1;
            }
            .pwa-banner-text h3 {
                margin: 0 0 0.5rem 0;
                font-size: 1rem;
                font-weight: 600;
            }
            .pwa-banner-text p {
                margin: 0;
                font-size: 0.875rem;
                color: var(--gray-600);
            }
            .pwa-banner-actions {
                display: flex;
                gap: 0.5rem;
            }
        `;
        document.head.appendChild(style);
        
        // Add event listeners
        banner.querySelector('#pwa-install-yes').addEventListener('click', () => {
            this.promptInstall();
        });
        
        banner.querySelector('#pwa-install-no').addEventListener('click', () => {
            this.hideInstallPrompt();
        });
        
        document.body.appendChild(banner);
    }
    
    /**
     * Add install button to dashboard
     */
    addInstallButtonToDashboard() {
        const existingButton = document.getElementById('pwa-install-button');
        if (existingButton) {
            return; // Already exists
        }
        
        // Add to header actions
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            const installButton = document.createElement('button');
            installButton.id = 'pwa-install-button';
            installButton.className = 'btn btn-outline';
            installButton.innerHTML = '<i class="fas fa-download"></i> Install App';
            installButton.addEventListener('click', () => {
                this.promptInstall();
            });
            
            headerActions.appendChild(installButton);
        }
    }
    
    /**
     * Prompt PWA installation
     */
    async promptInstall() {
        if (!this.installPrompt) {
            console.warn('Install prompt not available');
            return;
        }
        
        try {
            const result = await this.installPrompt.prompt();
            console.log('Install prompt result:', result);
            
            if (result.outcome === 'accepted') {
                console.log('User accepted installation');
            } else {
                console.log('User dismissed installation');
            }
            
            this.installPrompt = null;
            this.hideInstallPrompt();
            
        } catch (error) {
            console.error('Installation failed:', error);
        }
    }
    
    /**
     * Set up connectivity listeners
     */
    setupConnectivityListeners() {
        // Initial status
        this.isOffline = !navigator.onLine;
        this.onConnectivityChange(navigator.onLine);
        
        // Listen for connectivity changes
        window.addEventListener('online', () => {
            this.onConnectivityChange(true);
        });
        
        window.addEventListener('offline', () => {
            this.onConnectivityChange(false);
        });
    }
    
    /**
     * Handle connectivity change
     */
    onConnectivityChange(isOnline) {
        console.log('Connectivity changed:', isOnline ? 'online' : 'offline');
        
        // Update UI to reflect online/offline status
        this.updateConnectivityUI(isOnline);
        
        // Sync data when coming back online
        if (isOnline) {
            this.syncOfflineData();
        }
    }
    
    /**
     * Update connectivity UI
     */
    updateConnectivityUI(isOnline) {
        // Add/remove offline class to body
        document.body.classList.toggle('offline', !isOnline);
        
        // Update connection status indicator
        const statusIndicator = document.querySelector('.status-indicator');
        const statusText = document.querySelector('.status-text');
        
        if (statusIndicator) {
            statusIndicator.className = `status-indicator ${isOnline ? 'online' : 'offline'}`;
        }
        
        if (statusText) {
            statusText.textContent = isOnline ? 'Connected' : 'Offline';
        }
        
        // Show offline notification
        if (!isOnline) {
            this.showOfflineNotification();
        }
    }
    
    /**
     * Show offline notification
     */
    showOfflineNotification() {
        const notification = document.createElement('div');
        notification.className = 'pwa-offline-notification';
        notification.innerHTML = `
            <div class="offline-content">
                <i class="fas fa-wifi-slash"></i>
                <span>You're currently offline. Some features may be limited.</span>
            </div>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--warning-color);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            animation: slideInRight 0.3s ease-out;
        `;
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            .offline-content {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(notification);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
    
    /**
     * Initialize cache manager
     */
    async initializeCacheManager() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            // Get cache size
            navigator.serviceWorker.controller.postMessage({
                type: 'GET_CACHE_SIZE'
            });
            
            // Listen for cache size response
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data.type === 'CACHE_SIZE') {
                    this.cacheSize = event.data.size;
                    console.log('Cache size:', this.formatBytes(this.cacheSize));
                }
            });
        }
    }
    
    /**
     * Sync offline data when back online
     */
    async syncOfflineData() {
        try {
            // Trigger background sync
            if (this.serviceWorkerRegistration && 'sync' in this.serviceWorkerRegistration) {
                await this.serviceWorkerRegistration.sync.register('background-sync');
                console.log('Background sync registered');
            }
            
            // Refresh cached data
            await this.refreshCachedData();
            
            console.log('Offline data sync completed');
            
        } catch (error) {
            console.error('Failed to sync offline data:', error);
        }
    }
    
    /**
     * Refresh cached data
     */
    async refreshCachedData() {
        // Force update of cached API responses
        const cache = await caches.open(CACHE_NAME);
        const requests = await cache.keys();
        
        for (const request of requests) {
            try {
                const response = await fetch(request);
                if (response.ok) {
                    await cache.put(request, response.clone());
                }
            } catch (error) {
                console.log('Failed to refresh cached request:', request.url);
            }
        }
    }
    
    /**
     * Handle service worker messages
     */
    handleServiceWorkerMessage(message) {
        console.log('Message from service worker:', message);
        
        switch (message.type) {
            case 'CACHE_UPDATED':
                this.onCacheUpdated(message.data);
                break;
            case 'OFFLINE_FALLBACK':
                this.onOfflineFallback(message.data);
                break;
            case 'SYNC_COMPLETED':
                this.onSyncCompleted(message.data);
                break;
        }
    }
    
    /**
     * Handle cache updates
     */
    onCacheUpdated(data) {
        console.log('Cache updated:', data);
        this.cacheSize = data.size;
    }
    
    /**
     * Handle offline fallback
     */
    onOfflineFallback(data) {
        console.log('Offline fallback served:', data.url);
        this.showOfflineMessage(data.message);
    }
    
    /**
     * Handle sync completion
     */
    onSyncCompleted(data) {
        console.log('Background sync completed:', data);
        this.showSyncNotification();
    }
    
    /**
     * Show offline message
     */
    showOfflineMessage(message) {
        // Implementation for showing offline-specific messages
        console.log('Offline message:', message);
    }
    
    /**
     * Show sync notification
     */
    showSyncNotification() {
        // Implementation for showing sync completion notification
        console.log('Data synchronized successfully');
    }
    
    /**
     * Show update available notification
     */
    showUpdateAvailable() {
        const notification = document.createElement('div');
        notification.className = 'pwa-update-notification';
        notification.innerHTML = `
            <div class="update-content">
                <i class="fas fa-download"></i>
                <span>New version available. <button id="pwa-update-btn">Update Now</button></span>
            </div>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--info-color);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            z-index: 10000;
        `;
        
        notification.querySelector('#pwa-update-btn').addEventListener('click', () => {
            this.updateServiceWorker();
        });
        
        document.body.appendChild(notification);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 10000);
    }
    
    /**
     * Update service worker
     */
    updateServiceWorker() {
        if (this.serviceWorkerRegistration && this.serviceWorkerRegistration.waiting) {
            this.serviceWorkerRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
            window.location.reload();
        }
    }
    
    /**
     * Track installation event
     */
    trackInstallation() {
        // Track PWA installation for analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'pwa_install', {
                event_category: 'engagement',
                event_label: 'pwa_installation'
            });
        }
        
        console.log('PWA installation tracked');
    }
    
    /**
     * Format bytes to human readable format
     */
    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    /**
     * Get PWA status
     */
    getStatus() {
        return {
            isSupported: this.isPWASupported(),
            isInstalled: this.isInstalled,
            isOffline: this.isOffline,
            hasInstallPrompt: !!this.installPrompt,
            cacheSize: this.cacheSize,
            serviceWorkerRegistered: !!this.serviceWorkerRegistration
        };
    }
    
    /**
     * Clear app cache
     */
    async clearCache() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'CLEAR_CACHE',
                cacheName: CACHE_NAME
            });
        }
    }
}

// Initialize PWA Manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.aanpPWA = new AANP_PWA_Manager();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AANP_PWA_Manager;
}