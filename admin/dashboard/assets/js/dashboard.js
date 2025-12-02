/**
 * AI Auto News Poster Dashboard
 * Modern admin interface with real-time monitoring and analytics
 */

class AIDashboard {
    constructor() {
        this.websocket = null;
        this.charts = {};
        this.metrics = {};
        this.notifications = [];
        this.isConnected = false;
        this.monitoringInterval = null;
        this.updateInterval = 5000; // 5 seconds
        
        this.init();
    }

    /**
     * Initialize the dashboard
     */
    async init() {
        try {
            // Show loading overlay
            this.showLoadingOverlay();
            
            // Initialize components
            await this.initializeComponents();
            
            // Set up event listeners
            this.setupEventListeners();
            
            // Initialize WebSocket connection
            await this.initializeWebSocket();
            
            // Load initial data
            await this.loadDashboardData();
            
            // Start real-time monitoring
            this.startRealTimeMonitoring();
            
            // Hide loading overlay
            this.hideLoadingOverlay();
            
            console.log('Dashboard initialized successfully');
        } catch (error) {
            console.error('Dashboard initialization failed:', error);
            this.showNotification('Dashboard initialization failed', 'error');
        }
    }

    /**
     * Initialize dashboard components
     */
    async initializeComponents() {
        // Initialize charts
        this.initializeCharts();
        
        // Initialize metric counters
        this.initializeMetricCounters();
        
        // Setup theme
        this.setupTheme();
        
        // Setup accessibility
        this.setupAccessibility();
    }

    /**
     * Initialize all charts
     */
    initializeCharts() {
        // Performance chart
        const performanceCtx = document.getElementById('performance-chart');
        if (performanceCtx) {
            this.charts.performance = new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Performance Score',
                        data: [],
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 124, 186, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    },
                    animation: {
                        duration: 1000
                    }
                }
            });
        }

        // Activity chart
        const activityCtx = document.getElementById('activity-chart');
        if (activityCtx) {
            this.charts.activity = new Chart(activityCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Published', 'Scheduled', 'Draft'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: ['#28a745', '#ffc107', '#6c757d'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Response time chart
        const responseTimeCtx = document.getElementById('response-time-chart');
        if (responseTimeCtx) {
            this.charts.responseTime = new Chart(responseTimeCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Memory usage chart
        const memoryCtx = document.getElementById('memory-usage-chart');
        if (memoryCtx) {
            this.charts.memory = new Chart(memoryCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Memory Usage (MB)',
                        data: [],
                        borderColor: '#fd7e14',
                        backgroundColor: 'rgba(253, 126, 20, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // CPU usage chart
        const cpuCtx = document.getElementById('cpu-usage-chart');
        if (cpuCtx) {
            this.charts.cpu = new Chart(cpuCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'CPU Usage (%)',
                        data: [],
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }

    /**
     * Initialize metric counters with animation
     */
    initializeMetricCounters() {
        this.metricsCounters = {
            articlesPublished: document.getElementById('articles-published'),
            totalViews: document.getElementById('total-views'),
            performanceScore: document.getElementById('performance-score'),
            seoHealth: document.getElementById('seo-health'),
            responseTime: document.getElementById('response-time'),
            memoryUsage: document.getElementById('memory-usage'),
            cpuUsage: document.getElementById('cpu-usage'),
            totalRequests: document.getElementById('total-requests'),
            successRate: document.getElementById('success-rate')
        };
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Navigation
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchSection(link.dataset.section);
            });
        });

        // Refresh button
        const refreshBtn = document.getElementById('refresh-data');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refreshData());
        }

        // Start monitoring button
        const startMonitoringBtn = document.getElementById('start-monitoring');
        if (startMonitoringBtn) {
            startMonitoringBtn.addEventListener('click', () => this.toggleMonitoring());
        }

        // Time range selector
        const timeRangeSelect = document.getElementById('time-range');
        if (timeRangeSelect) {
            timeRangeSelect.addEventListener('change', () => this.updateTimeRange(timeRangeSelect.value));
        }

        // Chart controls
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.addEventListener('click', () => this.switchChart(btn.dataset.chart));
        });

        // Notification button
        const notificationBtn = document.getElementById('notifications-btn');
        if (notificationBtn) {
            notificationBtn.addEventListener('click', () => this.toggleNotifications());
        }

        // SEO audit button
        const seoAuditBtn = document.getElementById('run-seo-audit');
        if (seoAuditBtn) {
            seoAuditBtn.addEventListener('click', () => this.runSEOAudit());
        }

        // API key generation
        const generateApiKeyBtn = document.getElementById('generate-api-key');
        if (generateApiKeyBtn) {
            generateApiKeyBtn.addEventListener('click', () => this.generateAPIKey());
        }

        // Settings
        const saveSettingsBtn = document.getElementById('save-settings');
        if (saveSettingsBtn) {
            saveSettingsBtn.addEventListener('click', () => this.saveSettings());
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
    }

    /**
     * Initialize WebSocket connection for real-time updates
     */
    async initializeWebSocket() {
        try {
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = `${protocol}//${window.location.host}/wp-admin/admin-ajax.php?action=ai_news_websocket`;
            
            this.websocket = new WebSocket(wsUrl);
            
            this.websocket.onopen = () => {
                this.isConnected = true;
                this.updateConnectionStatus(true);
                console.log('WebSocket connected');
            };
            
            this.websocket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleWebSocketMessage(data);
                } catch (error) {
                    console.error('WebSocket message parsing error:', error);
                }
            };
            
            this.websocket.onclose = () => {
                this.isConnected = false;
                this.updateConnectionStatus(false);
                console.log('WebSocket disconnected');
                // Attempt reconnection after 5 seconds
                setTimeout(() => this.initializeWebSocket(), 5000);
            };
            
            this.websocket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.isConnected = false;
                this.updateConnectionStatus(false);
            };
            
        } catch (error) {
            console.error('WebSocket initialization failed:', error);
            this.isConnected = false;
            this.updateConnectionStatus(false);
        }
    }

    /**
     * Handle WebSocket messages
     */
    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'metric_update':
                this.updateMetrics(data.payload);
                break;
            case 'alert':
                this.handleAlert(data.payload);
                break;
            case 'notification':
                this.handleNotification(data.payload);
                break;
            case 'chart_data':
                this.updateChartData(data.payload);
                break;
            case 'seo_update':
                this.updateSEOData(data.payload);
                break;
            default:
                console.log('Unknown WebSocket message type:', data.type);
        }
    }

    /**
     * Switch between dashboard sections
     */
    switchSection(sectionName) {
        // Update navigation
        document.querySelectorAll('.menu-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-section="${sectionName}"]`).closest('.menu-item').classList.add('active');
        
        // Update content sections
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        document.getElementById(`${sectionName}-section`).classList.add('active');
        
        // Load section-specific data
        this.loadSectionData(sectionName);
    }

    /**
     * Load data for specific section
     */
    async loadSectionData(sectionName) {
        try {
            switch (sectionName) {
                case 'performance':
                    await this.loadPerformanceData();
                    break;
                case 'seo':
                    await this.loadSEOData();
                    break;
                case 'api':
                    await this.loadAPIData();
                    break;
                case 'content':
                    await this.loadContentData();
                    break;
            }
        } catch (error) {
            console.error(`Failed to load ${sectionName} data:`, error);
            this.showNotification(`Failed to load ${sectionName} data`, 'error');
        }
    }

    /**
     * Load initial dashboard data
     */
    async loadDashboardData() {
        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ai_news_get_dashboard_data',
                    nonce: ai_news_dashboard_nonce
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateMetrics(data.data.metrics);
                this.updateActivityList(data.data.activities);
                this.updatePerformanceCharts(data.data.performance);
                this.updateSEOData(data.data.seo);
            } else {
                throw new Error(data.data || 'Failed to load dashboard data');
            }
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            this.showNotification('Failed to load dashboard data', 'error');
        }
    }

    /**
     * Update metrics display
     */
    updateMetrics(metrics) {
        Object.entries(metrics).forEach(([key, value]) => {
            if (this.metricsCounters[key]) {
                this.animateCounter(this.metricsCounters[key], value);
            }
        });
        
        // Update percentage changes
        if (metrics.articles_change) {
            const changeElement = document.getElementById('articles-change');
            changeElement.textContent = `${metrics.articles_change > 0 ? '+' : ''}${metrics.articles_change}%`;
            changeElement.className = `metric-change ${metrics.articles_change >= 0 ? 'positive' : 'negative'}`;
        }
        
        if (metrics.views_change) {
            const changeElement = document.getElementById('views-change');
            changeElement.textContent = `${metrics.views_change > 0 ? '+' : ''}${metrics.views_change}%`;
            changeElement.className = `metric-change ${metrics.views_change >= 0 ? 'positive' : 'negative'}`;
        }
    }

    /**
     * Animate counter from current to target value
     */
    animateCounter(element, target, duration = 1000) {
        const start = parseFloat(element.textContent) || 0;
        const increment = (target - start) / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= target) || (increment < 0 && current <= target)) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.round(current).toLocaleString();
        }, 16);
    }

    /**
     * Update activity list
     */
    updateActivityList(activities) {
        const activityList = document.getElementById('activity-list');
        if (!activityList) return;
        
        activityList.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon ${activity.type}">
                    <i class="fas ${activity.icon}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.title}</div>
                    <div class="activity-description">${activity.description}</div>
                    <div class="activity-time">${this.formatTimeAgo(activity.timestamp)}</div>
                </div>
            </div>
        `).join('');
    }

    /**
     * Update chart data
     */
    updateChartData(chartData) {
        if (this.charts[chartData.chartType]) {
            const chart = this.charts[chartData.chartType];
            chart.data.labels = chartData.labels;
            chart.data.datasets[0].data = chartData.data;
            chart.update('active');
        }
    }

    /**
     * Start real-time monitoring
     */
    startRealTimeMonitoring() {
        this.monitoringInterval = setInterval(() => {
            this.fetchRealTimeData();
        }, this.updateInterval);
    }

    /**
     * Fetch real-time data
     */
    async fetchRealTimeData() {
        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ai_news_get_realtime_data',
                    nonce: ai_news_dashboard_nonce
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateMetrics(data.data.metrics);
                this.updateRealTimeCharts(data.data.charts);
            }
        } catch (error) {
            console.error('Failed to fetch real-time data:', error);
        }
    }

    /**
     * Update real-time charts
     */
    updateRealTimeCharts(chartData) {
        const now = new Date().toLocaleTimeString();
        
        Object.entries(chartData).forEach(([chartType, value]) => {
            if (this.charts[chartType]) {
                const chart = this.charts[chartType];
                
                // Add new data point
                chart.data.labels.push(now);
                chart.data.datasets[0].data.push(value);
                
                // Keep only last 20 data points
                if (chart.data.labels.length > 20) {
                    chart.data.labels.shift();
                    chart.data.datasets[0].data.shift();
                }
                
                chart.update('none');
            }
        });
    }

    /**
     * Handle alert
     */
    handleAlert(alert) {
        this.showNotification(alert.message, alert.severity);
        
        if (alert.severity === 'error') {
            this.addAlertToList(alert);
        }
    }

    /**
     * Add alert to performance alerts list
     */
    addAlertToList(alert) {
        const alertsList = document.getElementById('performance-alerts');
        if (!alertsList) return;
        
        const alertElement = document.createElement('div');
        alertElement.className = `alert-item ${alert.severity}`;
        alertElement.innerHTML = `
            <div class="alert-content">
                <div class="alert-title">${alert.title}</div>
                <div class="alert-message">${alert.message}</div>
            </div>
        `;
        
        alertsList.insertBefore(alertElement, alertsList.firstChild);
        
        // Remove alerts older than 10 items
        while (alertsList.children.length > 10) {
            alertsList.removeChild(alertsList.lastChild);
        }
    }

    /**
     * Update SEO data
     */
    updateSEOData(seoData) {
        // Update overall score
        const overallScoreElement = document.getElementById('overall-seo-score');
        if (overallScoreElement) {
            const scoreCircle = overallScoreElement;
            const percentage = seoData.overall_score;
            scoreCircle.style.background = `conic-gradient(#007cba 0deg, #007cba ${percentage * 3.6}deg, #e5e7eb ${percentage * 3.6}deg)`;
            
            const scoreValue = scoreCircle.querySelector('.score-value');
            if (scoreValue) {
                this.animateCounter(scoreValue, percentage);
            }
        }
        
        // Update individual scores
        ['content-quality', 'eeat', 'technical-seo'].forEach(metric => {
            const element = document.getElementById(`${metric}-score`);
            if (element && seoData[metric]) {
                this.animateCounter(element, seoData[metric]);
            }
        });
        
        // Update status
        const seoStatus = document.getElementById('seo-status');
        if (seoStatus) {
            const score = seoData.overall_score;
            let status = 'Loading...';
            let statusClass = '';
            
            if (score >= 80) {
                status = 'Excellent';
                statusClass = 'excellent';
            } else if (score >= 60) {
                status = 'Good';
                statusClass = 'good';
            } else {
                status = 'Needs Improvement';
                statusClass = 'poor';
            }
            
            seoStatus.textContent = status;
            seoStatus.className = `metric-status ${statusClass}`;
        }
        
        // Update recommendations
        this.updateSEORecommendations(seoData.recommendations || []);
    }

    /**
     * Update SEO recommendations
     */
    updateSEORecommendations(recommendations) {
        const recommendationsList = document.getElementById('seo-recommendations');
        if (!recommendationsList) return;
        
        recommendationsList.innerHTML = recommendations.map(rec => `
            <div class="recommendation-item">
                <div class="recommendation-title">${rec.title}</div>
                <div class="recommendation-description">${rec.description}</div>
            </div>
        `).join('');
    }

    /**
     * Run SEO audit
     */
    async runSEOAudit() {
        const button = document.getElementById('run-seo-audit');
        if (!button) return;
        
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Audit...';
        button.disabled = true;
        
        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ai_news_run_seo_audit',
                    nonce: ai_news_dashboard_nonce
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateSEOData(data.data);
                this.showNotification('SEO audit completed successfully', 'success');
            } else {
                throw new Error(data.data || 'SEO audit failed');
            }
        } catch (error) {
            console.error('SEO audit failed:', error);
            this.showNotification('SEO audit failed', 'error');
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }

    /**
     * Generate API key
     */
    async generateAPIKey() {
        const button = document.getElementById('generate-api-key');
        if (!button) return;
        
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        button.disabled = true;
        
        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ai_news_generate_api_key',
                    nonce: ai_news_dashboard_nonce
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('API key generated successfully', 'success');
                // You might want to display the key in a modal or copy it to clipboard
                this.copyToClipboard(data.data.api_key);
            } else {
                throw new Error(data.data || 'Failed to generate API key');
            }
        } catch (error) {
            console.error('API key generation failed:', error);
            this.showNotification('Failed to generate API key', 'error');
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }

    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showNotification('Copied to clipboard', 'info');
        } catch (error) {
            console.error('Failed to copy to clipboard:', error);
        }
    }

    /**
     * Refresh data
     */
    async refreshData() {
        this.showLoadingOverlay();
        await this.loadDashboardData();
        this.hideLoadingOverlay();
        this.showNotification('Data refreshed successfully', 'success');
    }

    /**
     * Toggle monitoring
     */
    toggleMonitoring() {
        const button = document.getElementById('start-monitoring');
        if (!button) return;
        
        if (this.monitoringInterval) {
            clearInterval(this.monitoringInterval);
            this.monitoringInterval = null;
            button.innerHTML = '<i class="fas fa-play"></i> Start Monitoring';
            this.showNotification('Monitoring stopped', 'info');
        } else {
            this.startRealTimeMonitoring();
            button.innerHTML = '<i class="fas fa-pause"></i> Stop Monitoring';
            this.showNotification('Monitoring started', 'info');
        }
    }

    /**
     * Update connection status indicator
     */
    updateConnectionStatus(connected) {
        const statusIndicator = document.getElementById('connection-status');
        const statusText = document.querySelector('.status-text');
        
        if (statusIndicator) {
            statusIndicator.className = `status-indicator ${connected ? 'online' : 'offline'}`;
        }
        
        if (statusText) {
            statusText.textContent = connected ? 'Connected' : 'Disconnected';
        }
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span>${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;
        
        // Add to notifications array for the dropdown
        this.notifications.unshift({
            id: Date.now(),
            message,
            type,
            timestamp: new Date(),
            read: false
        });
        
        // Update notification count
        this.updateNotificationCount();
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
        
        // Close button functionality
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        });
    }

    /**
     * Update notification count
     */
    updateNotificationCount() {
        const countElement = document.getElementById('notification-count');
        const unreadCount = this.notifications.filter(n => !n.read).length;
        
        if (countElement) {
            countElement.textContent = unreadCount;
            countElement.style.display = unreadCount > 0 ? 'block' : 'none';
        }
    }

    /**
     * Toggle notifications dropdown
     */
    toggleNotifications() {
        const panel = document.getElementById('notification-panel');
        if (!panel) return;
        
        if (panel.classList.contains('show')) {
            panel.classList.remove('show');
        } else {
            this.renderNotificationsDropdown();
            panel.classList.add('show');
        }
    }

    /**
     * Render notifications dropdown
     */
    renderNotificationsDropdown() {
        const panel = document.getElementById('notification-panel');
        if (!panel) return;
        
        const recentNotifications = this.notifications.slice(0, 10);
        
        panel.innerHTML = `
            <div class="notification-panel-header">
                <h4>Notifications</h4>
                <button class="mark-all-read">Mark all read</button>
            </div>
            <div class="notification-list">
                ${recentNotifications.map(notification => `
                    <div class="notification-item ${notification.read ? 'read' : 'unread'}">
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${this.formatTimeAgo(notification.timestamp)}</div>
                    </div>
                `).join('')}
                ${recentNotifications.length === 0 ? '<div class="no-notifications">No notifications</div>' : ''}
            </div>
        `;
        
        // Mark all read button
        const markAllReadBtn = panel.querySelector('.mark-all-read');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', () => {
                this.notifications.forEach(n => n.read = true);
                this.updateNotificationCount();
                this.renderNotificationsDropdown();
            });
        }
    }

    /**
     * Handle keyboard shortcuts
     */
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + R: Refresh data
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            this.refreshData();
        }
        
        // Ctrl/Cmd + 1-6: Switch sections
        if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '6') {
            e.preventDefault();
            const sections = ['overview', 'performance', 'content', 'seo', 'api', 'settings'];
            const sectionIndex = parseInt(e.key) - 1;
            if (sections[sectionIndex]) {
                this.switchSection(sections[sectionIndex]);
            }
        }
    }

    /**
     * Setup theme
     */
    setupTheme() {
        const themeSelect = document.getElementById('theme-select');
        if (themeSelect) {
            // Load saved theme
            const savedTheme = localStorage.getItem('dashboard-theme') || 'light';
            themeSelect.value = savedTheme;
            this.applyTheme(savedTheme);
            
            themeSelect.addEventListener('change', (e) => {
                this.applyTheme(e.target.value);
                localStorage.setItem('dashboard-theme', e.target.value);
            });
        }
    }

    /**
     * Apply theme
     */
    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
    }

    /**
     * Setup accessibility features
     */
    setupAccessibility() {
        // Add skip link
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.textContent = 'Skip to main content';
        skipLink.className = 'skip-link';
        document.body.insertBefore(skipLink, document.body.firstChild);
        
        // Add main content id
        const dashboardMain = document.querySelector('.dashboard-main');
        if (dashboardMain) {
            dashboardMain.id = 'main-content';
        }
        
        // Improve focus management
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });
        
        document.addEventListener('mousedown', () => {
            document.body.classList.remove('keyboard-navigation');
        });
    }

    /**
     * Show loading overlay
     */
    showLoadingOverlay() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    /**
     * Hide loading overlay
     */
    hideLoadingOverlay() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    /**
     * Format time ago
     */
    formatTimeAgo(date) {
        const now = new Date();
        const diff = now - new Date(date);
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        return `${days} day${days > 1 ? 's' : ''} ago`;
    }

    /**
     * Utility method to format numbers
     */
    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    /**
     * Cleanup method
     */
    destroy() {
        // Clear intervals
        if (this.monitoringInterval) {
            clearInterval(this.monitoringInterval);
        }
        
        // Close WebSocket
        if (this.websocket) {
            this.websocket.close();
        }
        
        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.aiDashboard = new AIDashboard();
});

// Handle page visibility changes
document.addEventListener('visibilitychange', () => {
    if (window.aiDashboard) {
        if (document.hidden) {
            // Page is hidden, reduce update frequency
            if (window.aiDashboard.monitoringInterval) {
                clearInterval(window.aiDashboard.monitoringInterval);
                window.aiDashboard.monitoringInterval = setInterval(() => {
                    window.aiDashboard.fetchRealTimeData();
                }, 30000); // 30 seconds when hidden
            }
        } else {
            // Page is visible, restore normal update frequency
            if (window.aiDashboard.monitoringInterval) {
                clearInterval(window.aiDashboard.monitoringInterval);
                window.aiDashboard.startRealTimeMonitoring();
            }
        }
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.aiDashboard) {
        window.aiDashboard.destroy();
    }
});

// Export for potential module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AIDashboard;
}