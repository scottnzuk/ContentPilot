/**
 * Performance Monitoring and Alerting System for AI Auto News Poster Dashboard
 * 
 * Provides real-time performance monitoring, threshold-based alerting,
 * and comprehensive alert management with automated responses.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

class AANP_PerformanceMonitoring {
    
    /**
     * Alert configuration
     */
    private config = {
        updateInterval: 3000, // 3 seconds
        historyRetention: 1000, // Number of alerts to retain
        alertCooldown: 300000, // 5 minutes between similar alerts
        checkInterval: 5000, // 5 seconds for threshold checks
        maxAlertsPerMinute: 10
    };
    
    /**
     * Performance thresholds
     */
    private thresholds = {
        responseTime: {
            warning: 1000, // 1 second
            critical: 3000, // 3 seconds
            unit: 'ms'
        },
        memoryUsage: {
            warning: 80, // 80%
            critical: 95, // 95%
            unit: '%'
        },
        cpuUsage: {
            warning: 70, // 70%
            critical: 90, // 90%
            unit: '%'
        },
        databaseQueries: {
            warning: 50, // 50 queries
            critical: 100, // 100 queries
            unit: 'queries'
        },
        cacheHitRate: {
            warning: 70, // 70% (low hit rate is bad)
            critical: 50, // 50% (low hit rate is critical)
            unit: '%'
        },
        errorRate: {
            warning: 5, // 5%
            critical: 15, // 15%
            unit: '%'
        },
        apiRequests: {
            warning: 1000, // 1000 requests/minute
            critical: 2000, // 2000 requests/minute
            unit: 'requests/min'
        },
        diskUsage: {
            warning: 85, // 85%
            critical: 95, // 95%
            unit: '%'
        },
        loadAverage: {
            warning: 2.0, // Load average
            critical: 4.0, // Load average
            unit: 'load'
        }
    };
    
    /**
     * Alert states
     */
    private alertStates = new Map();
    private activeAlerts = new Map();
    private alertHistory = [];
    private suppressedAlerts = new Set();
    
    /**
     * Monitoring data
     */
    private currentMetrics = {};
    private previousMetrics = {};
    private metricHistory = new Map();
    
    /**
     * WebSocket for real-time alerts
     */
    private websocket = null;
    private notificationPermission = null;
    
    /**
     * DOM elements
     */
    private alertPanel = null;
    private alertIndicators = new Map();
    private alertCounter = null;
    
    /**
     * Alert severity levels
     */
    private severityLevels = {
        info: { color: '#17a2b8', icon: 'info-circle', priority: 1 },
        warning: { color: '#ffc107', icon: 'exclamation-triangle', priority: 2 },
        critical: { color: '#dc3545', icon: 'exclamation-circle', priority: 3 },
        emergency: { color: '#6f42c1', icon: 'bomb', priority: 4 }
    };
    
    constructor() {
        this.init();
    }
    
    /**
     * Initialize performance monitoring
     */
    async init() {
        try {
            // Request notification permission
            await this.requestNotificationPermission();
            
            // Setup monitoring components
            this.setupMonitoring();
            this.setupAlertPanel();
            this.setupAlertIndicators();
            this.setupWebSocketConnection();
            
            // Start monitoring
            this.startMonitoring();
            
            // Setup cleanup
            this.setupCleanup();
            
            console.log('Performance Monitoring initialized');
            
        } catch (error) {
            console.error('Failed to initialize Performance Monitoring:', error);
        }
    }
    
    /**
     * Request notification permission
     */
    async requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            try {
                const permission = await Notification.requestPermission();
                this.notificationPermission = permission;
                console.log('Notification permission:', permission);
            } catch (error) {
                console.warn('Failed to request notification permission:', error);
            }
        } else {
            this.notificationPermission = Notification.permission;
        }
    }
    
    /**
     * Setup monitoring components
     */
    setupMonitoring() {
        // Initialize metric history
        Object.keys(this.thresholds).forEach(metric => {
            this.metricHistory.set(metric, []);
        });
        
        // Setup real-time data collection
        this.setupDataCollection();
        
        // Setup threshold checking
        this.setupThresholdChecking();
        
        // Setup alert management
        this.setupAlertManagement();
    }
    
    /**
     * Setup data collection
     */
    setupDataCollection() {
        // Collect initial metrics
        this.collectMetrics();
        
        // Setup periodic collection
        setInterval(() => {
            this.collectMetrics();
        }, this.config.updateInterval);
    }
    
    /**
     * Collect current performance metrics
     */
    async collectMetrics() {
        try {
            // Simulate metric collection (in real implementation, this would collect actual metrics)
            const metrics = await this.getPerformanceMetrics();
            
            // Store previous metrics
            this.previousMetrics = { ...this.currentMetrics };
            
            // Update current metrics
            this.currentMetrics = { ...metrics, timestamp: Date.now() };
            
            // Store in history
            this.updateMetricHistory(metrics);
            
            // Check thresholds
            this.checkThresholds();
            
            // Update dashboard indicators
            this.updateDashboardIndicators();
            
        } catch (error) {
            console.error('Failed to collect metrics:', error);
            this.handleMetricCollectionError(error);
        }
    }
    
    /**
     * Get performance metrics (simulated)
     */
    async getPerformanceMetrics() {
        // In a real implementation, this would collect actual performance data
        // For now, we'll simulate realistic metrics with some variance
        
        const baseMetrics = {
            responseTime: Math.random() * 2000 + 100,
            memoryUsage: Math.random() * 60 + 20,
            cpuUsage: Math.random() * 50 + 10,
            databaseQueries: Math.floor(Math.random() * 80) + 5,
            cacheHitRate: Math.random() * 30 + 70,
            errorRate: Math.random() * 10,
            apiRequests: Math.floor(Math.random() * 1500) + 100,
            diskUsage: Math.random() * 40 + 30,
            loadAverage: Math.random() * 3 + 0.5
        };
        
        // Add some realistic spikes occasionally
        if (Math.random() > 0.9) {
            baseMetrics.responseTime *= 2;
            baseMetrics.cpuUsage *= 1.5;
        }
        
        if (Math.random() > 0.95) {
            baseMetrics.errorRate *= 3;
            baseMetrics.databaseQueries *= 2;
        }
        
        return baseMetrics;
    }
    
    /**
     * Handle metric collection error
     */
    handleMetricCollectionError(error) {
        // Create alert for metric collection failure
        this.createAlert({
            type: 'metric_collection_error',
            severity: 'critical',
            title: 'Metric Collection Failed',
            message: 'Unable to collect performance metrics: ' + error.message,
            metric: 'system',
            value: 0,
            threshold: 'N/A',
            timestamp: Date.now()
        });
    }
    
    /**
     * Update metric history
     */
    updateMetricHistory(metrics) {
        Object.keys(metrics).forEach(metric => {
            const history = this.metricHistory.get(metric);
            if (history) {
                history.push({
                    value: metrics[metric],
                    timestamp: Date.now()
                });
                
                // Limit history size
                if (history.length > 100) {
                    history.shift();
                }
            }
        });
    }
    
    /**
     * Setup threshold checking
     */
    setupThresholdChecking() {
        setInterval(() => {
            this.checkAllThresholds();
        }, this.config.checkInterval);
    }
    
    /**
     * Check individual threshold
     */
    checkThreshold(metricName, value) {
        const threshold = this.thresholds[metricName];
        if (!threshold) return null;
        
        const stateKey = `${metricName}_threshold`;
        let severity = null;
        
        if (value >= threshold.critical) {
            severity = 'critical';
        } else if (value >= threshold.warning) {
            severity = 'warning';
        }
        
        if (severity) {
            // Check cooldown period
            const lastAlert = this.alertStates.get(stateKey);
            const now = Date.now();
            
            if (!lastAlert || (now - lastAlert.timestamp) > this.config.alertCooldown) {
                // Create alert
                this.createAlert({
                    type: 'threshold_exceeded',
                    severity: severity,
                    title: this.getAlertTitle(metricName, severity),
                    message: this.getAlertMessage(metricName, value, threshold, severity),
                    metric: metricName,
                    value: value,
                    threshold: threshold[severity],
                    timestamp: now
                });
                
                // Update state
                this.alertStates.set(stateKey, {
                    severity: severity,
                    timestamp: now,
                    value: value
                });
                
                return severity;
            }
        } else {
            // Check for recovery
            this.checkRecovery(metricName, value);
        }
        
        return severity;
    }
    
    /**
     * Check all thresholds
     */
    checkAllThresholds() {
        Object.keys(this.currentMetrics).forEach(metricName => {
            if (metricName !== 'timestamp' && this.thresholds[metricName]) {
                this.checkThreshold(metricName, this.currentMetrics[metricName]);
            }
        });
    }
    
    /**
     * Check threshold for single metric
     */
    checkThresholds() {
        Object.keys(this.currentMetrics).forEach(metricName => {
            if (metricName !== 'timestamp' && this.thresholds[metricName]) {
                this.checkThreshold(metricName, this.currentMetrics[metricName]);
            }
        });
    }
    
    /**
     * Check for metric recovery
     */
    checkRecovery(metricName, currentValue) {
        const stateKey = `${metricName}_threshold`;
        const state = this.alertStates.get(stateKey);
        
        if (state) {
            const threshold = this.thresholds[metricName];
            const recoveryValue = state.severity === 'critical' ? threshold.warning : 0;
            
            if (currentValue < recoveryValue) {
                // Create recovery alert
                this.createAlert({
                    type: 'threshold_recovered',
                    severity: 'info',
                    title: this.getRecoveryTitle(metricName),
                    message: this.getRecoveryMessage(metricName, currentValue),
                    metric: metricName,
                    value: currentValue,
                    threshold: recoveryValue,
                    timestamp: Date.now(),
                    isRecovery: true
                });
                
                // Clear state
                this.alertStates.delete(stateKey);
            }
        }
    }
    
    /**
     * Setup alert management
     */
    setupAlertManagement() {
        // Clean up old alerts periodically
        setInterval(() => {
            this.cleanupAlerts();
        }, 60000); // Every minute
        
        // Check alert rate limiting
        setInterval(() => {
            this.checkAlertRateLimit();
        }, 60000); // Every minute
    }
    
    /**
     * Create new alert
     */
    createAlert(alertData) {
        const alert = {
            id: this.generateAlertId(),
            ...alertData,
            acknowledged: false,
            resolved: false,
            actions: []
        };
        
        // Add to active alerts if not recovery
        if (!alert.isRecovery) {
            this.activeAlerts.set(alert.id, alert);
        }
        
        // Add to history
        this.alertHistory.push(alert);
        
        // Trim history if needed
        if (this.alertHistory.length > this.config.historyRetention) {
            this.alertHistory.shift();
        }
        
        // Process alert
        this.processAlert(alert);
        
        console.log('Alert created:', alert);
        
        return alert;
    }
    
    /**
     * Process alert
     */
    processAlert(alert) {
        // Show notification
        this.showAlertNotification(alert);
        
        // Update UI
        this.updateAlertUI(alert);
        
        // Play sound if enabled
        this.playAlertSound(alert);
        
        // Execute automated actions
        this.executeAutomatedActions(alert);
        
        // Send to server for logging
        this.logAlert(alert);
        
        // Update badge counter
        this.updateAlertCounter();
    }
    
    /**
     * Show alert notification
     */
    showAlertNotification(alert) {
        if (this.notificationPermission === 'granted') {
            try {
                const notification = new Notification(alert.title, {
                    body: alert.message,
                    icon: this.getAlertIcon(alert.severity),
                    badge: this.getAlertIcon(alert.severity),
                    tag: alert.id,
                    requireInteraction: alert.severity === 'critical' || alert.severity === 'emergency',
                    silent: alert.isRecovery
                });
                
                notification.onclick = () => {
                    window.focus();
                    this.showAlertDetails(alert.id);
                    notification.close();
                };
                
                // Auto-close after delay for non-critical alerts
                if (alert.severity !== 'critical' && alert.severity !== 'emergency') {
                    setTimeout(() => {
                        notification.close();
                    }, 5000);
                }
                
            } catch (error) {
                console.warn('Failed to show notification:', error);
            }
        }
    }
    
    /**
     * Update alert UI
     */
    updateAlertUI(alert) {
        // Update alert panel
        this.updateAlertPanel(alert);
        
        // Update dashboard indicators
        this.updateDashboardIndicator(alert.metric, alert);
        
        // Update browser title if critical alert
        if (alert.severity === 'critical' || alert.severity === 'emergency') {
            this.updateBrowserTitle(true);
        }
    }
    
    /**
     * Play alert sound
     */
    playAlertSound(alert) {
        if (alert.severity === 'critical' || alert.severity === 'emergency') {
            // Play critical alert sound
            this.playSound('critical');
        } else if (alert.severity === 'warning') {
            // Play warning sound
            this.playSound('warning');
        }
    }
    
    /**
     * Execute automated actions
     */
    executeAutomatedActions(alert) {
        switch (alert.severity) {
            case 'critical':
            case 'emergency':
                this.executeCriticalActions(alert);
                break;
            case 'warning':
                this.executeWarningActions(alert);
                break;
        }
    }
    
    /**
     * Execute critical severity actions
     */
    executeCriticalActions(alert) {
        // Clear caches
        this.clearCaches();
        
        // Restart services if needed
        if (alert.type === 'memory_usage_critical') {
            this.restartMemoryIntensiveProcesses();
        }
        
        // Send emergency notification
        this.sendEmergencyNotification(alert);
    }
    
    /**
     * Execute warning severity actions
     */
    executeWarningActions(alert) {
        // Log warning
        this.logWarning(alert);
        
        // Update monitoring frequency
        this.increaseMonitoringFrequency(alert.metric);
    }
    
    /**
     * Setup alert panel
     */
    setupAlertPanel() {
        // Create alert panel if it doesn't exist
        let alertPanel = document.querySelector('.alert-panel');
        if (!alertPanel) {
            alertPanel = this.createAlertPanel();
        }
        this.alertPanel = alertPanel;
    }
    
    /**
     * Create alert panel
     */
    createAlertPanel() {
        const panel = document.createElement('div');
        panel.className = 'alert-panel';
        panel.innerHTML = `
            <div class="alert-panel-header">
                <h3><i class="fas fa-bell"></i> Performance Alerts</h3>
                <div class="alert-panel-actions">
                    <button class="btn btn-sm btn-outline" id="clear-all-alerts">Clear All</button>
                    <button class="btn btn-sm btn-outline" id="alert-settings">Settings</button>
                </div>
            </div>
            <div class="alert-panel-body">
                <div class="alert-filters">
                    <select id="alert-severity-filter">
                        <option value="all">All Severities</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="critical">Critical</option>
                        <option value="emergency">Emergency</option>
                    </select>
                    <select id="alert-status-filter">
                        <option value="active">Active</option>
                        <option value="acknowledged">Acknowledged</option>
                        <option value="resolved">Resolved</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="alert-list" id="alert-list"></div>
            </div>
        `;
        
        // Add to dashboard
        const dashboard = document.querySelector('.dashboard-content');
        if (dashboard) {
            dashboard.appendChild(panel);
        }
        
        // Setup event listeners
        this.setupAlertPanelEvents(panel);
        
        return panel;
    }
    
    /**
     * Setup alert panel events
     */
    setupAlertPanelEvents(panel) {
        // Clear all alerts
        panel.querySelector('#clear-all-alerts').addEventListener('click', () => {
            this.clearAllAlerts();
        });
        
        // Alert settings
        panel.querySelector('#alert-settings').addEventListener('click', () => {
            this.showAlertSettings();
        });
        
        // Filters
        panel.querySelector('#alert-severity-filter').addEventListener('change', () => {
            this.filterAlerts();
        });
        
        panel.querySelector('#alert-status-filter').addEventListener('change', () => {
            this.filterAlerts();
        });
    }
    
    /**
     * Setup alert indicators
     */
    setupAlertIndicators() {
        // Create alert counter
        this.createAlertCounter();
        
        // Create metric indicators
        this.createMetricIndicators();
    }
    
    /**
     * Create alert counter
     */
    createAlertCounter() {
        const header = document.querySelector('.dashboard-header') || document.querySelector('.header-content');
        if (!header) return;
        
        const counter = document.createElement('div');
        counter.className = 'alert-counter';
        counter.innerHTML = `
            <button class="alert-counter-btn">
                <i class="fas fa-bell"></i>
                <span class="alert-count">0</span>
            </button>
        `;
        
        counter.querySelector('.alert-counter-btn').addEventListener('click', () => {
            this.toggleAlertPanel();
        });
        
        header.appendChild(counter);
        this.alertCounter = counter;
    }
    
    /**
     * Create metric indicators
     */
    createMetricIndicators() {
        // Create indicators for key metrics
        const keyMetrics = ['responseTime', 'memoryUsage', 'cpuUsage', 'cacheHitRate'];
        
        keyMetrics.forEach(metric => {
            this.createMetricIndicator(metric);
        });
    }
    
    /**
     * Create individual metric indicator
     */
    createMetricIndicator(metricName) {
        // Find existing indicator or create new one
        let indicator = document.querySelector(`[data-metric="${metricName}"] .metric-indicator`);
        if (!indicator) {
            // Try to find the metric element
            const metricElement = document.querySelector(`[data-metric="${metricName}"]`) ||
                                document.querySelector(`.metric-${metricName}`);
            
            if (metricElement) {
                indicator = document.createElement('div');
                indicator.className = 'metric-indicator';
                indicator.innerHTML = '<i class="fas fa-circle"></i>';
                metricElement.appendChild(indicator);
            }
        }
        
        if (indicator) {
            this.alertIndicators.set(metricName, indicator);
        }
    }
    
    /**
     * Update dashboard indicators
     */
    updateDashboardIndicators() {
        this.activeAlerts.forEach(alert => {
            this.updateDashboardIndicator(alert.metric, alert);
        });
    }
    
    /**
     * Update individual dashboard indicator
     */
    updateDashboardIndicator(metricName, alert) {
        const indicator = this.alertIndicators.get(metricName);
        if (!indicator) return;
        
        const icon = indicator.querySelector('i');
        if (!icon) return;
        
        // Remove existing severity classes
        indicator.classList.remove('alert-info', 'alert-warning', 'alert-critical', 'alert-emergency');
        
        // Add new severity class
        indicator.classList.add(`alert-${alert.severity}`);
        
        // Update icon
        icon.className = `fas fa-${this.severityLevels[alert.severity].icon}`;
        
        // Add pulse animation for critical alerts
        if (alert.severity === 'critical' || alert.severity === 'emergency') {
            indicator.classList.add('pulse');
        } else {
            indicator.classList.remove('pulse');
        }
    }
    
    /**
     * Update alert counter
     */
    updateAlertCounter() {
        if (!this.alertCounter) return;
        
        const count = this.activeAlerts.size;
        const counterElement = this.alertCounter.querySelector('.alert-count');
        
        if (counterElement) {
            counterElement.textContent = count;
            counterElement.style.display = count > 0 ? 'inline' : 'none';
        }
        
        // Add visual indication for critical alerts
        const hasCritical = Array.from(this.activeAlerts.values())
            .some(alert => alert.severity === 'critical' || alert.severity === 'emergency');
        
        this.alertCounter.classList.toggle('has-critical', hasCritical);
    }
    
    /**
     * Update alert panel
     */
    updateAlertPanel(alert) {
        const alertList = this.alertPanel?.querySelector('#alert-list');
        if (!alertList) return;
        
        // Create alert item
        const alertItem = this.createAlertItem(alert);
        alertList.insertBefore(alertItem, alertList.firstChild);
        
        // Limit number of displayed alerts
        while (alertList.children.length > 50) {
            alertList.removeChild(alertList.lastChild);
        }
    }
    
    /**
     * Create alert item element
     */
    createAlertItem(alert) {
        const item = document.createElement('div');
        item.className = `alert-item alert-${alert.severity}`;
        item.dataset.alertId = alert.id;
        
        item.innerHTML = `
            <div class="alert-item-header">
                <div class="alert-severity">
                    <i class="fas fa-${this.severityLevels[alert.severity].icon}"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-title">${alert.title}</div>
                    <div class="alert-message">${alert.message}</div>
                    <div class="alert-meta">
                        <span class="alert-time">${this.formatTime(alert.timestamp)}</span>
                        <span class="alert-metric">${alert.metric}</span>
                    </div>
                </div>
                <div class="alert-actions">
                    ${!alert.isRecovery ? `
                        <button class="btn btn-sm btn-outline acknowledge-btn">Acknowledge</button>
                        <button class="btn btn-sm btn-outline resolve-btn">Resolve</button>
                    ` : ''}
                    <button class="btn btn-sm btn-outline details-btn">Details</button>
                </div>
            </div>
        `;
        
        // Setup event listeners
        this.setupAlertItemEvents(item, alert);
        
        return item;
    }
    
    /**
     * Setup alert item events
     */
    setupAlertItemEvents(item, alert) {
        // Acknowledge button
        const ackBtn = item.querySelector('.acknowledge-btn');
        if (ackBtn) {
            ackBtn.addEventListener('click', () => {
                this.acknowledgeAlert(alert.id);
            });
        }
        
        // Resolve button
        const resolveBtn = item.querySelector('.resolve-btn');
        if (resolveBtn) {
            resolveBtn.addEventListener('click', () => {
                this.resolveAlert(alert.id);
            });
        }
        
        // Details button
        const detailsBtn = item.querySelector('.details-btn');
        detailsBtn.addEventListener('click', () => {
            this.showAlertDetails(alert.id);
        });
    }
    
    /**
     * Toggle alert panel
     */
    toggleAlertPanel() {
        if (!this.alertPanel) return;
        
        this.alertPanel.classList.toggle('open');
        
        // Close panel when clicking outside
        if (this.alertPanel.classList.contains('open')) {
            setTimeout(() => {
                document.addEventListener('click', this.closeAlertPanelOnOutsideClick);
            }, 100);
        }
    }
    
    /**
     * Close alert panel on outside click
     */
    closeAlertPanelOnOutsideClick = (e) => {
        if (!this.alertPanel.contains(e.target) && !this.alertCounter.contains(e.target)) {
            this.alertPanel.classList.remove('open');
            document.removeEventListener('click', this.closeAlertPanelOnOutsideClick);
        }
    }
    
    /**
     * Filter alerts
     */
    filterAlerts() {
        const severityFilter = document.querySelector('#alert-severity-filter')?.value || 'all';
        const statusFilter = document.querySelector('#alert-status-filter')?.value || 'active';
        
        const alertList = this.alertPanel?.querySelector('#alert-list');
        if (!alertList) return;
        
        Array.from(alertList.children).forEach(item => {
            const alertId = item.dataset.alertId;
            const alert = this.activeAlerts.get(alertId) || this.alertHistory.find(a => a.id === alertId);
            
            if (!alert) return;
            
            let show = true;
            
            // Filter by severity
            if (severityFilter !== 'all' && alert.severity !== severityFilter) {
                show = false;
            }
            
            // Filter by status
            if (statusFilter !== 'all') {
                if (statusFilter === 'active' && (alert.acknowledged || alert.resolved)) {
                    show = false;
                } else if (statusFilter === 'acknowledged' && !alert.acknowledged) {
                    show = false;
                } else if (statusFilter === 'resolved' && !alert.resolved) {
                    show = false;
                }
            }
            
            item.style.display = show ? 'block' : 'none';
        });
    }
    
    /**
     * Acknowledge alert
     */
    acknowledgeAlert(alertId) {
        const alert = this.activeAlerts.get(alertId);
        if (alert) {
            alert.acknowledged = true;
            alert.acknowledgedAt = Date.now();
            
            // Update UI
            this.updateAlertItem(alertId);
            
            console.log('Alert acknowledged:', alertId);
        }
    }
    
    /**
     * Resolve alert
     */
    resolveAlert(alertId) {
        const alert = this.activeAlerts.get(alertId);
        if (alert) {
            alert.resolved = true;
            alert.resolvedAt = Date.now();
            
            // Remove from active alerts
            this.activeAlerts.delete(alertId);
            
            // Update UI
            const item = document.querySelector(`[data-alert-id="${alertId}"]`);
            if (item) {
                item.style.opacity = '0.5';
                setTimeout(() => {
                    item.remove();
                }, 300);
            }
            
            // Update counter
            this.updateAlertCounter();
            
            console.log('Alert resolved:', alertId);
        }
    }
    
    /**
     * Clear all alerts
     */
    clearAllAlerts() {
        this.activeAlerts.clear();
        this.alertHistory = [];
        
        // Clear UI
        const alertList = this.alertPanel?.querySelector('#alert-list');
        if (alertList) {
            alertList.innerHTML = '';
        }
        
        // Reset indicators
        this.alertIndicators.forEach(indicator => {
            indicator.classList.remove('alert-info', 'alert-warning', 'alert-critical', 'alert-emergency', 'pulse');
            const icon = indicator.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-circle';
            }
        });
        
        // Update counter
        this.updateAlertCounter();
        
        // Reset browser title
        this.updateBrowserTitle(false);
        
        console.log('All alerts cleared');
    }
    
    /**
     * Show alert details
     */
    showAlertDetails(alertId) {
        const alert = this.activeAlerts.get(alertId) || this.alertHistory.find(a => a.id === alertId);
        if (!alert) return;
        
        // Create modal or details panel
        this.createAlertDetailsModal(alert);
    }
    
    /**
     * Create alert details modal
     */
    createAlertDetailsModal(alert) {
        const modal = document.createElement('div');
        modal.className = 'alert-details-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Alert Details</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert-detail-item">
                        <label>Severity:</label>
                        <span class="severity-badge ${alert.severity}">${alert.severity.toUpperCase()}</span>
                    </div>
                    <div class="alert-detail-item">
                        <label>Title:</label>
                        <span>${alert.title}</span>
                    </div>
                    <div class="alert-detail-item">
                        <label>Message:</label>
                        <span>${alert.message}</span>
                    </div>
                    <div class="alert-detail-item">
                        <label>Metric:</label>
                        <span>${alert.metric}</span>
                    </div>
                    <div class="alert-detail-item">
                        <label>Current Value:</label>
                        <span>${alert.value}</span>
                    </div>
                    <div class="alert-detail-item">
                        <label>Threshold:</label>
                        <span>${alert.threshold}</span>
                    </div>
                    <div class="alert-detail-item">
                        <label>Time:</label>
                        <span>${new Date(alert.timestamp).toLocaleString()}</span>
                    </div>
                    ${alert.acknowledged ? `
                        <div class="alert-detail-item">
                            <label>Acknowledged:</label>
                            <span>${new Date(alert.acknowledgedAt).toLocaleString()}</span>
                        </div>
                    ` : ''}
                    ${alert.resolved ? `
                        <div class="alert-detail-item">
                            <label>Resolved:</label>
                            <span>${new Date(alert.resolvedAt).toLocaleString()}</span>
                        </div>
                    ` : ''}
                </div>
                <div class="modal-footer">
                    ${!alert.acknowledged ? '<button class="btn btn-primary acknowledge-btn">Acknowledge</button>' : ''}
                    ${!alert.resolved ? '<button class="btn btn-success resolve-btn">Resolve</button>' : ''}
                    <button class="btn btn-outline close-btn">Close</button>
                </div>
            </div>
        `;
        
        // Add event listeners
        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.querySelector('.close-btn').addEventListener('click', () => {
            modal.remove();
        });
        
        const ackBtn = modal.querySelector('.acknowledge-btn');
        if (ackBtn) {
            ackBtn.addEventListener('click', () => {
                this.acknowledgeAlert(alert.id);
                modal.remove();
            });
        }
        
        const resolveBtn = modal.querySelector('.resolve-btn');
        if (resolveBtn) {
            resolveBtn.addEventListener('click', () => {
                this.resolveAlert(alert.id);
                modal.remove();
            });
        }
        
        document.body.appendChild(modal);
        
        // Auto-focus for accessibility
        modal.focus();
    }
    
    /**
     * Setup WebSocket connection for real-time alerts
     */
    setupWebSocketConnection() {
        // In a real implementation, this would connect to a WebSocket server
        // For now, we'll simulate real-time alert reception
        console.log('WebSocket setup for real-time alerts (simulated)');
        
        // Simulate receiving alerts
        setInterval(() => {
            if (Math.random() > 0.95) { // 5% chance
                this.simulateIncomingAlert();
            }
        }, 10000); // Every 10 seconds
    }
    
    /**
     * Simulate incoming alert (for testing)
     */
    simulateIncomingAlert() {
        const metrics = Object.keys(this.thresholds);
        const metric = metrics[Math.floor(Math.random() * metrics.length)];
        const value = Math.random() * 3000;
        
        this.createAlert({
            type: 'simulated_alert',
            severity: Math.random() > 0.7 ? 'critical' : 'warning',
            title: `Simulated ${metric} Alert`,
            message: `Simulated alert for ${metric}: ${value.toFixed(2)}`,
            metric: metric,
            value: value,
            threshold: this.thresholds[metric].warning,
            timestamp: Date.now()
        });
    }
    
    /**
     * Start monitoring
     */
    startMonitoring() {
        console.log('Performance monitoring started');
        
        // Send startup notification
        if (this.notificationPermission === 'granted') {
            new Notification('Performance Monitoring', {
                body: 'Monitoring system started successfully',
                icon: this.getAlertIcon('info')
            });
        }
    }
    
    /**
     * Setup cleanup
     */
    setupCleanup() {
        // Cleanup old data periodically
        setInterval(() => {
            this.performCleanup();
        }, 300000); // Every 5 minutes
    }
    
    /**
     * Perform cleanup
     */
    performCleanup() {
        // Clean up old metric history
        this.metricHistory.forEach((history, metric) => {
            if (history.length > 100) {
                history.splice(0, history.length - 100);
            }
        });
        
        // Clean up old resolved alerts from UI
        this.cleanupResolvedAlerts();
        
        console.log('Performance monitoring cleanup completed');
    }
    
    /**
     * Cleanup resolved alerts
     */
    cleanupResolvedAlerts() {
        const alertList = this.alertPanel?.querySelector('#alert-list');
        if (!alertList) return;
        
        Array.from(alertList.children).forEach(item => {
            const alertId = item.dataset.alertId;
            const alert = this.alertHistory.find(a => a.id === alertId);
            
            if (alert && alert.resolved && alert.resolvedAt) {
                const age = Date.now() - alert.resolvedAt;
                if (age > 3600000) { // 1 hour
                    item.remove();
                }
            }
        });
    }
    
    /**
     * Helper methods
     */
    
    generateAlertId() {
        return 'alert_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    getAlertTitle(metricName, severity) {
        const titles = {
            responseTime: {
                warning: 'High Response Time',
                critical: 'Critical Response Time'
            },
            memoryUsage: {
                warning: 'High Memory Usage',
                critical: 'Critical Memory Usage'
            },
            cpuUsage: {
                warning: 'High CPU Usage',
                critical: 'Critical CPU Usage'
            },
            databaseQueries: {
                warning: 'High Database Load',
                critical: 'Critical Database Load'
            },
            cacheHitRate: {
                warning: 'Low Cache Hit Rate',
                critical: 'Critical Cache Hit Rate'
            },
            errorRate: {
                warning: 'High Error Rate',
                critical: 'Critical Error Rate'
            }
        };
        
        return titles[metricName]?.[severity] || `${severity} ${metricName} Alert`;
    }
    
    getAlertMessage(metricName, value, threshold, severity) {
        const unit = threshold.unit;
        const thresholdValue = threshold[severity];
        
        return `${metricName} is ${value.toFixed(2)} ${unit} (${severity} threshold: ${thresholdValue} ${unit})`;
    }
    
    getRecoveryTitle(metricName) {
        return `${metricName} Recovered`;
    }
    
    getRecoveryMessage(metricName, currentValue) {
        return `${metricName} has returned to normal levels: ${currentValue.toFixed(2)}`;
    }
    
    getAlertIcon(severity) {
        const icons = {
            info: '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/icons/info.png',
            warning: '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/icons/warning.png',
            critical: '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/icons/critical.png',
            emergency: '/wp-content/plugins/ai-auto-news-poster/admin/dashboard/assets/icons/emergency.png'
        };
        return icons[severity] || icons.info;
    }
    
    formatTime(timestamp) {
        const now = Date.now();
        const diff = now - timestamp;
        
        if (diff < 60000) { // Less than 1 minute
            return 'Just now';
        } else if (diff < 3600000) { // Less than 1 hour
            return Math.floor(diff / 60000) + 'm ago';
        } else if (diff < 86400000) { // Less than 1 day
            return Math.floor(diff / 3600000) + 'h ago';
        } else {
            return Math.floor(diff / 86400000) + 'd ago';
        }
    }
    
    playSound(type) {
        // In a real implementation, this would play appropriate alert sounds
        console.log(`Playing ${type} alert sound`);
    }
    
    updateBrowserTitle(hasCriticalAlerts) {
        const originalTitle = document.title.replace(/^\(\d+\)\s*/, '');
        if (hasCriticalAlerts) {
            const criticalCount = Array.from(this.activeAlerts.values())
                .filter(alert => alert.severity === 'critical' || alert.severity === 'emergency').length;
            document.title = `(${criticalCount}) ${originalTitle}`;
        } else {
            document.title = originalTitle;
        }
    }
    
    // Additional automated action methods
    clearCaches() {
        console.log('Clearing caches due to critical alert');
        // Implementation would clear various caches
    }
    
    restartMemoryIntensiveProcesses() {
        console.log('Restarting memory intensive processes');
        // Implementation would restart heavy processes
    }
    
    sendEmergencyNotification(alert) {
        console.log('Sending emergency notification for:', alert);
        // Implementation would send emergency notifications
    }
    
    logWarning(alert) {
        console.warn('Performance warning:', alert);
        // Implementation would log warnings
    }
    
    increaseMonitoringFrequency(metric) {
        console.log(`Increased monitoring frequency for ${metric}`);
        // Implementation would increase monitoring frequency
    }
    
    logAlert(alert) {
        // Implementation would log alert to server
        console.log('Logging alert:', alert);
    }
    
    checkAlertRateLimit() {
        // Implementation would check and enforce alert rate limits
        const recentAlerts = this.alertHistory.filter(alert => 
            Date.now() - alert.timestamp < 60000
        );
        
        if (recentAlerts.length > this.config.maxAlertsPerMinute) {
            console.warn('Alert rate limit exceeded');
            // Implement rate limiting
        }
    }
    
    cleanupAlerts() {
        // Remove old alerts from history
        const cutoff = Date.now() - (24 * 60 * 60 * 1000); // 24 hours
        this.alertHistory = this.alertHistory.filter(alert => alert.timestamp > cutoff);
    }
    
    showAlertSettings() {
        console.log('Showing alert settings');
        // Implementation would show alert configuration settings
    }
    
    updateAlertItem(alertId) {
        const item = document.querySelector(`[data-alert-id="${alertId}"]`);
        if (item) {
            const ackBtn = item.querySelector('.acknowledge-btn');
            if (ackBtn) {
                ackBtn.textContent = 'Acknowledged';
                ackBtn.disabled = true;
                ackBtn.classList.add('btn-success');
            }
        }
    }
    
    /**
     * Get monitoring state
     */
    getState() {
        return {
            activeAlerts: this.activeAlerts.size,
            totalAlerts: this.alertHistory.length,
            currentMetrics: this.currentMetrics,
            thresholds: this.thresholds,
            config: this.config
        };
    }
    
    /**
     * Destroy monitoring
     */
    destroy() {
        // Clear intervals
        // Close WebSocket
        // Remove event listeners
        
        console.log('Performance Monitoring destroyed');
    }
}

// Initialize monitoring when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.aanpMonitoring = new AANP_PerformanceMonitoring();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.aanpMonitoring) {
        window.aanpMonitoring.destroy();
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AANP_PerformanceMonitoring;
}