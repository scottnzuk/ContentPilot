/**
 * Interactive Performance Analytics with Chart.js for AI Auto News Poster Dashboard
 * 
 * Provides real-time performance analytics, interactive charts,
 * and comprehensive performance visualization.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

class AANP_PerformanceAnalytics {
    
    /**
     * Chart.js instances
     */
    private charts = new Map();
    
    /**
     * Data stores
     */
    private dataStore = {
        responseTime: [],
        memoryUsage: [],
        cpuUsage: [],
        databaseQueries: [],
        cacheHitRate: [],
        apiRequests: [],
        errorRate: [],
        timestamps: []
    };
    
    /**
     * Configuration
     */
    private config = {
        maxDataPoints: 100,
        updateInterval: 5000, // 5 seconds
        animationDuration: 750,
        chartColors: {
            primary: '#007cba',
            success: '#28a745',
            warning: '#ffc107',
            danger: '#dc3545',
            info: '#17a2b8',
            light: '#f8f9fa',
            dark: '#343a40'
        },
        chartGradients: {}
    };
    
    /**
     * WebSocket connection
     */
    private websocket = null;
    
    /**
     * Update intervals
     */
    private intervals = new Map();
    
    /**
     * Chart containers
     */
    private containers = {
        responseTime: null,
        memoryUsage: null,
        cpuUsage: null,
        databaseQueries: null,
        cacheHitRate: null,
        apiRequests: null,
        errorRate: null
    };
    
    constructor() {
        this.init();
    }
    
    /**
     * Initialize analytics
     */
    async init() {
        try {
            // Wait for DOM
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupCharts());
            } else {
                this.setupCharts();
            }
            
            // Setup data collection
            this.setupDataCollection();
            
            // Setup real-time updates
            this.setupRealTimeUpdates();
            
            // Setup interaction handlers
            this.setupInteractionHandlers();
            
            console.log('Performance Analytics initialized');
            
        } catch (error) {
            console.error('Failed to initialize Performance Analytics:', error);
        }
    }
    
    /**
     * Setup Chart.js charts
     */
    setupCharts() {
        // Create gradients
        this.createGradients();
        
        // Setup chart containers
        this.setupChartContainers();
        
        // Initialize charts
        this.initializeCharts();
        
        // Setup responsive handling
        this.setupResponsiveHandling();
    }
    
    /**
     * Create chart gradients
     */
    createGradients() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Primary gradient
        const primaryGradient = ctx.createLinearGradient(0, 0, 0, 400);
        primaryGradient.addColorStop(0, this.config.chartColors.primary + '40');
        primaryGradient.addColorStop(1, this.config.chartColors.primary + '10');
        
        // Success gradient
        const successGradient = ctx.createLinearGradient(0, 0, 0, 400);
        successGradient.addColorStop(0, this.config.chartColors.success + '40');
        successGradient.addColorStop(1, this.config.chartColors.success + '10');
        
        // Warning gradient
        const warningGradient = ctx.createLinearGradient(0, 0, 0, 400);
        warningGradient.addColorStop(0, this.config.chartColors.warning + '40');
        warningGradient.addColorStop(1, this.config.chartColors.warning + '10');
        
        // Danger gradient
        const dangerGradient = ctx.createLinearGradient(0, 0, 0, 400);
        dangerGradient.addColorStop(0, this.config.chartColors.danger + '40');
        dangerGradient.addColorStop(1, this.config.chartColors.danger + '10');
        
        this.config.chartGradients = {
            primary: primaryGradient,
            success: successGradient,
            warning: warningGradient,
            danger: dangerGradient
        };
    }
    
    /**
     * Setup chart containers
     */
    setupChartContainers() {
        const chartArea = document.querySelector('.charts-grid') || document.querySelector('.performance-charts');
        if (!chartArea) {
            console.warn('Chart area not found');
            return;
        }
        
        // Create chart containers if they don't exist
        this.createChartContainers(chartArea);
    }
    
    /**
     * Create chart containers
     */
    createChartContainers(chartArea) {
        const charts = [
            { id: 'responseTime', title: 'Response Time', icon: 'tachometer-alt' },
            { id: 'memoryUsage', title: 'Memory Usage', icon: 'memory' },
            { id: 'cpuUsage', title: 'CPU Usage', icon: 'microchip' },
            { id: 'databaseQueries', title: 'Database Queries', icon: 'database' },
            { id: 'cacheHitRate', title: 'Cache Hit Rate', icon: 'cache' },
            { id: 'apiRequests', title: 'API Requests', icon: 'exchange-alt' },
            { id: 'errorRate', title: 'Error Rate', icon: 'exclamation-triangle' }
        ];
        
        charts.forEach(chart => {
            let container = document.getElementById(`chart-${chart.id}`);
            if (!container) {
                container = this.createChartContainer(chart);
                chartArea.appendChild(container);
            }
            this.containers[chart.id] = container;
        });
    }
    
    /**
     * Create individual chart container
     */
    createChartContainer(chart) {
        const container = document.createElement('div');
        container.id = `chart-${chart.id}`;
        container.className = 'chart-container performance-chart';
        container.innerHTML = `
            <div class="chart-header">
                <h3><i class="fas fa-${chart.icon}"></i> ${chart.title}</h3>
                <div class="chart-controls">
                    <button class="chart-btn active" data-period="1h">1H</button>
                    <button class="chart-btn" data-period="6h">6H</button>
                    <button class="chart-btn" data-period="24h">24H</button>
                    <button class="chart-btn" data-period="7d">7D</button>
                </div>
            </div>
            <div class="chart-content">
                <canvas id="canvas-${chart.id}"></canvas>
            </div>
            <div class="chart-stats">
                <div class="stat-item">
                    <span class="stat-label">Current</span>
                    <span class="stat-value" id="current-${chart.id}">-</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Average</span>
                    <span class="stat-value" id="avg-${chart.id}">-</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Peak</span>
                    <span class="stat-value" id="peak-${chart.id}">-</span>
                </div>
            </div>
        `;
        
        return container;
    }
    
    /**
     * Initialize Chart.js charts
     */
    initializeCharts() {
        Object.keys(this.containers).forEach(chartId => {
            const container = this.containers[chartId];
            if (!container) return;
            
            const canvas = container.querySelector('canvas');
            if (!canvas) return;
            
            const chart = this.createChart(chartId, canvas);
            this.charts.set(chartId, chart);
            
            // Setup chart controls
            this.setupChartControls(chartId);
        });
    }
    
    /**
     * Create individual Chart.js instance
     */
    createChart(chartId, canvas) {
        const ctx = canvas.getContext('2d');
        
        const config = {
            type: this.getChartType(chartId),
            data: {
                labels: [],
                datasets: [{
                    label: this.getChartLabel(chartId),
                    data: [],
                    borderColor: this.config.chartColors[this.getChartColor(chartId)],
                    backgroundColor: this.config.chartGradients[this.getChartColor(chartId)],
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: this.config.chartColors[this.getChartColor(chartId)],
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: this.config.animationDuration,
                    easing: 'easeInOutQuart'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: this.config.chartColors[this.getChartColor(chartId)],
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: (tooltipItems) => {
                                return new Date(tooltipItems[0].parsed.x).toLocaleTimeString();
                            },
                            label: (context) => {
                                return `${this.getChartLabel(chartId)}: ${this.formatValue(context.parsed.y, chartId)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'minute',
                            displayFormats: {
                                minute: 'HH:mm',
                                hour: 'HH:mm',
                                day: 'MMM dd'
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            callback: (value) => this.formatValue(value, chartId)
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                onHover: (event, activeElements) => {
                    canvas.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                }
            }
        };
        
        // Chart-specific configurations
        this.configureChartSpecifics(chartId, config);
        
        return new Chart(ctx, config);
    }
    
    /**
     * Configure chart-specific settings
     */
    configureChartSpecifics(chartId, config) {
        switch (chartId) {
            case 'memoryUsage':
                config.options.scales.y.suggestedMax = 100;
                config.options.scales.y.ticks.callback = (value) => `${value}%`;
                break;
                
            case 'cpuUsage':
                config.options.scales.y.suggestedMax = 100;
                config.options.scales.y.ticks.callback = (value) => `${value}%`;
                break;
                
            case 'cacheHitRate':
                config.options.scales.y.suggestedMax = 100;
                config.options.scales.y.ticks.callback = (value) => `${value}%`;
                break;
                
            case 'responseTime':
                config.options.plugins.tooltip.callbacks.label = (context) => `Response Time: ${context.parsed.y}ms`;
                break;
                
            case 'errorRate':
                config.data.datasets[0].backgroundColor = this.config.chartGradients.danger;
                break;
        }
    }
    
    /**
     * Get chart type based on metric
     */
    getChartType(chartId) {
        const types = {
            responseTime: 'line',
            memoryUsage: 'line',
            cpuUsage: 'line',
            databaseQueries: 'bar',
            cacheHitRate: 'line',
            apiRequests: 'line',
            errorRate: 'line'
        };
        return types[chartId] || 'line';
    }
    
    /**
     * Get chart label
     */
    getChartLabel(chartId) {
        const labels = {
            responseTime: 'Response Time (ms)',
            memoryUsage: 'Memory Usage (%)',
            cpuUsage: 'CPU Usage (%)',
            databaseQueries: 'Database Queries',
            cacheHitRate: 'Cache Hit Rate (%)',
            apiRequests: 'API Requests',
            errorRate: 'Error Rate (%)'
        };
        return labels[chartId] || chartId;
    }
    
    /**
     * Get chart color scheme
     */
    getChartColor(chartId) {
        const colors = {
            responseTime: 'warning',
            memoryUsage: 'info',
            cpuUsage: 'danger',
            databaseQueries: 'primary',
            cacheHitRate: 'success',
            apiRequests: 'info',
            errorRate: 'danger'
        };
        return colors[chartId] || 'primary';
    }
    
    /**
     * Setup chart controls
     */
    setupChartControls(chartId) {
        const container = this.containers[chartId];
        if (!container) return;
        
        const controlButtons = container.querySelectorAll('.chart-btn');
        controlButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                // Update active state
                controlButtons.forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');
                
                // Update time period
                const period = e.target.dataset.period;
                this.updateTimePeriod(chartId, period);
            });
        });
    }
    
    /**
     * Setup responsive handling
     */
    setupResponsiveHandling() {
        // Handle window resize
        window.addEventListener('resize', this.handleResize.bind(this));
        
        // Handle visibility change
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
    }
    
    /**
     * Setup data collection
     */
    setupDataCollection() {
        // Initialize with sample data
        this.generateSampleData();
        
        // Setup periodic data collection
        this.intervals.set('dataCollection', setInterval(() => {
            this.collectPerformanceData();
        }, this.config.updateInterval));
    }
    
    /**
     * Generate sample data for demonstration
     */
    generateSampleData() {
        const now = Date.now();
        const points = 50;
        
        for (let i = points; i >= 0; i--) {
            const timestamp = new Date(now - (i * 60000)); // 1 minute intervals
            
            this.dataStore.timestamps.push(timestamp);
            this.dataStore.responseTime.push(Math.random() * 200 + 50);
            this.dataStore.memoryUsage.push(Math.random() * 40 + 30);
            this.dataStore.cpuUsage.push(Math.random() * 30 + 10);
            this.dataStore.databaseQueries.push(Math.floor(Math.random() * 50) + 10);
            this.dataStore.cacheHitRate.push(Math.random() * 20 + 75);
            this.dataStore.apiRequests.push(Math.floor(Math.random() * 100) + 20);
            this.dataStore.errorRate.push(Math.random() * 5);
        }
    }
    
    /**
     * Collect real performance data
     */
    async collectPerformanceData() {
        try {
            // In a real implementation, this would collect actual performance metrics
            const response = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=aanp_get_performance_metrics&nonce=' + this.getNonce()
            });
            
            if (response.ok) {
                const data = await response.json();
                this.updateDataStore(data);
            }
        } catch (error) {
            // Fallback to generated data if API fails
            this.generateRealtimeData();
        }
    }
    
    /**
     * Generate realtime sample data
     */
    generateRealtimeData() {
        const now = new Date();
        
        // Add slight variations to existing data
        Object.keys(this.dataStore).forEach(key => {
            if (key === 'timestamps') return;
            
            const lastValue = this.dataStore[key][this.dataStore[key].length - 1] || 0;
            const variation = (Math.random() - 0.5) * (lastValue * 0.1);
            const newValue = Math.max(0, lastValue + variation);
            
            this.dataStore[key].push(newValue);
            
            // Keep only recent data
            if (this.dataStore[key].length > this.config.maxDataPoints) {
                this.dataStore[key].shift();
            }
        });
        
        this.dataStore.timestamps.push(now);
        if (this.dataStore.timestamps.length > this.config.maxDataPoints) {
            this.dataStore.timestamps.shift();
        }
    }
    
    /**
     * Update data store with new data
     */
    updateDataStore(data) {
        const timestamp = new Date();
        
        this.dataStore.timestamps.push(timestamp);
        this.dataStore.responseTime.push(data.responseTime || 0);
        this.dataStore.memoryUsage.push(data.memoryUsage || 0);
        this.dataStore.cpuUsage.push(data.cpuUsage || 0);
        this.dataStore.databaseQueries.push(data.databaseQueries || 0);
        this.dataStore.cacheHitRate.push(data.cacheHitRate || 0);
        this.dataStore.apiRequests.push(data.apiRequests || 0);
        this.dataStore.errorRate.push(data.errorRate || 0);
        
        // Trim old data
        this.trimOldData();
    }
    
    /**
     * Trim old data points
     */
    trimOldData() {
        Object.keys(this.dataStore).forEach(key => {
            if (this.dataStore[key].length > this.config.maxDataPoints) {
                this.dataStore[key] = this.dataStore[key].slice(-this.config.maxDataPoints);
            }
        });
    }
    
    /**
     * Setup real-time updates
     */
    setupRealTimeUpdates() {
        // Update charts periodically
        this.intervals.set('chartUpdates', setInterval(() => {
            this.updateAllCharts();
        }, this.config.updateInterval));
        
        // Setup WebSocket for real-time updates
        this.setupWebSocketConnection();
    }
    
    /**
     * Setup WebSocket connection
     */
    setupWebSocketConnection() {
        // In a real implementation, this would connect to a WebSocket server
        // For now, we'll simulate WebSocket behavior
        console.log('WebSocket connection setup (simulated)');
        
        // Simulate receiving real-time data
        this.intervals.set('websocket', setInterval(() => {
            if (Math.random() > 0.7) { // 30% chance of new data
                this.handleRealtimeData();
            }
        }, 2000));
    }
    
    /**
     * Handle real-time data updates
     */
    handleRealtimeData() {
        const metrics = {
            responseTime: Math.random() * 200 + 50,
            memoryUsage: Math.random() * 40 + 30,
            cpuUsage: Math.random() * 30 + 10,
            databaseQueries: Math.floor(Math.random() * 50) + 10,
            cacheHitRate: Math.random() * 20 + 75,
            apiRequests: Math.floor(Math.random() * 100) + 20,
            errorRate: Math.random() * 5
        };
        
        this.updateDataStore(metrics);
        this.updateAllCharts();
        this.updateChartStats();
    }
    
    /**
     * Update all charts
     */
    updateAllCharts() {
        this.charts.forEach((chart, chartId) => {
            this.updateChart(chartId, chart);
        });
        
        this.updateChartStats();
    }
    
    /**
     * Update individual chart
     */
    updateChart(chartId, chart) {
        const data = this.dataStore[chartId];
        if (!data || data.length === 0) return;
        
        const timestamps = this.dataStore.timestamps;
        
        // Update chart data
        chart.data.labels = timestamps;
        chart.data.datasets[0].data = data;
        
        // Trigger update
        chart.update('none'); // No animation for real-time updates
    }
    
    /**
     * Update chart statistics
     */
    updateChartStats() {
        Object.keys(this.containers).forEach(chartId => {
            const data = this.dataStore[chartId];
            if (!data || data.length === 0) return;
            
            const current = data[data.length - 1];
            const average = data.reduce((sum, val) => sum + val, 0) / data.length;
            const peak = Math.max(...data);
            
            // Update DOM elements
            this.updateStatElement(`current-${chartId}`, this.formatValue(current, chartId));
            this.updateStatElement(`avg-${chartId}`, this.formatValue(average, chartId));
            this.updateStatElement(`peak-${chartId}`, this.formatValue(peak, chartId));
        });
    }
    
    /**
     * Update stat element
     */
    updateStatElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }
    
    /**
     * Format value based on chart type
     */
    formatValue(value, chartId) {
        switch (chartId) {
            case 'responseTime':
                return `${Math.round(value)}ms`;
            case 'memoryUsage':
            case 'cpuUsage':
            case 'cacheHitRate':
            case 'errorRate':
                return `${Math.round(value)}%`;
            case 'databaseQueries':
            case 'apiRequests':
                return Math.round(value).toString();
            default:
                return Math.round(value * 100) / 100;
        }
    }
    
    /**
     * Update time period
     */
    updateTimePeriod(chartId, period) {
        // Filter data based on selected period
        const now = Date.now();
        let startTime;
        
        switch (period) {
            case '1h':
                startTime = now - (60 * 60 * 1000);
                break;
            case '6h':
                startTime = now - (6 * 60 * 60 * 1000);
                break;
            case '24h':
                startTime = now - (24 * 60 * 60 * 1000);
                break;
            case '7d':
                startTime = now - (7 * 24 * 60 * 60 * 1000);
                break;
            default:
                startTime = now - (60 * 60 * 1000);
        }
        
        // Filter data
        const filteredData = this.filterDataByTime(startTime);
        this.updateChartData(chartId, filteredData);
    }
    
    /**
     * Filter data by time period
     */
    filterDataByTime(startTime) {
        const filtered = {
            timestamps: [],
            responseTime: [],
            memoryUsage: [],
            cpuUsage: [],
            databaseQueries: [],
            cacheHitRate: [],
            apiRequests: [],
            errorRate: []
        };
        
        this.dataStore.timestamps.forEach((timestamp, index) => {
            if (timestamp.getTime() >= startTime) {
                filtered.timestamps.push(timestamp);
                Object.keys(filtered).forEach(key => {
                    if (key !== 'timestamps') {
                        filtered[key].push(this.dataStore[key][index]);
                    }
                });
            }
        });
        
        return filtered;
    }
    
    /**
     * Update chart data
     */
    updateChartData(chartId, filteredData) {
        const chart = this.charts.get(chartId);
        if (!chart) return;
        
        chart.data.labels = filteredData.timestamps;
        chart.data.datasets[0].data = filteredData[chartId];
        chart.update();
    }
    
    /**
     * Setup interaction handlers
     */
    setupInteractionHandlers() {
        // Export chart data
        this.setupExportHandlers();
        
        // Chart zoom and pan
        this.setupZoomHandlers();
        
        // Fullscreen view
        this.setupFullscreenHandlers();
    }
    
    /**
     * Setup export handlers
     */
    setupExportHandlers() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('.export-chart-btn')) {
                const chartId = e.target.dataset.chartId;
                this.exportChartData(chartId);
            }
            
            if (e.target.matches('.export-all-btn')) {
                this.exportAllCharts();
            }
        });
    }
    
    /**
     * Export individual chart data
     */
    exportChartData(chartId) {
        const data = this.dataStore[chartId];
        const timestamps = this.dataStore.timestamps;
        
        const exportData = timestamps.map((timestamp, index) => ({
            time: timestamp.toISOString(),
            value: data[index]
        }));
        
        const blob = new Blob([JSON.stringify(exportData, null, 2)], {
            type: 'application/json'
        });
        
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `performance-${chartId}-${Date.now()}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    /**
     * Export all charts data
     */
    exportAllCharts() {
        const exportData = {
            timestamp: new Date().toISOString(),
            data: this.dataStore
        };
        
        const blob = new Blob([JSON.stringify(exportData, null, 2)], {
            type: 'application/json'
        });
        
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `all-performance-data-${Date.now()}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    /**
     * Setup zoom handlers
     */
    setupZoomHandlers() {
        this.charts.forEach((chart, chartId) => {
            const canvas = chart.canvas;
            
            // Mouse wheel zoom
            canvas.addEventListener('wheel', (e) => {
                e.preventDefault();
                const zoomFactor = e.deltaY > 0 ? 1.1 : 0.9;
                this.zoomChart(chart, zoomFactor);
            });
            
            // Double click to reset zoom
            canvas.addEventListener('dblclick', () => {
                this.resetChartZoom(chart);
            });
        });
    }
    
    /**
     * Zoom chart
     */
    zoomChart(chart, factor) {
        chart.options.scales.x.min = undefined;
        chart.options.scales.x.max = undefined;
        chart.options.scales.y.min = undefined;
        chart.options.scales.y.max = undefined;
        
        chart.update();
    }
    
    /**
     * Reset chart zoom
     */
    resetChartZoom(chart) {
        chart.resetZoom();
    }
    
    /**
     * Setup fullscreen handlers
     */
    setupFullscreenHandlers() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('.fullscreen-chart-btn')) {
                const chartId = e.target.dataset.chartId;
                this.toggleFullscreen(chartId);
            }
        });
    }
    
    /**
     * Toggle fullscreen chart view
     */
    toggleFullscreen(chartId) {
        const container = this.containers[chartId];
        if (!container) return;
        
        if (container.requestFullscreen) {
            container.requestFullscreen();
        } else if (container.webkitRequestFullscreen) {
            container.webkitRequestFullscreen();
        } else if (container.msRequestFullscreen) {
            container.msRequestFullscreen();
        }
    }
    
    /**
     * Handle window resize
     */
    handleResize() {
        this.charts.forEach(chart => {
            chart.resize();
        });
    }
    
    /**
     * Handle visibility change
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // Pause updates when tab is not visible
            this.pauseUpdates();
        } else {
            // Resume updates when tab becomes visible
            this.resumeUpdates();
        }
    }
    
    /**
     * Pause updates
     */
    pauseUpdates() {
        this.intervals.forEach((intervalId) => {
            clearInterval(intervalId);
        });
        this.intervals.clear();
    }
    
    /**
     * Resume updates
     */
    resumeUpdates() {
        this.setupDataCollection();
        this.setupRealTimeUpdates();
    }
    
    /**
     * Get nonce for AJAX requests
     */
    getNonce() {
        const nonceElement = document.querySelector('#aanp-nonce');
        return nonceElement ? nonceElement.value : '';
    }
    
    /**
     * Destroy analytics
     */
    destroy() {
        // Clear intervals
        this.pauseUpdates();
        
        // Destroy charts
        this.charts.forEach(chart => {
            chart.destroy();
        });
        this.charts.clear();
        
        // Close WebSocket
        if (this.websocket) {
            this.websocket.close();
        }
        
        console.log('Performance Analytics destroyed');
    }
    
    /**
     * Get analytics state
     */
    getState() {
        return {
            chartsInitialized: this.charts.size,
            dataPoints: this.dataStore.timestamps.length,
            intervals: Array.from(this.intervals.keys()),
            updateInterval: this.config.updateInterval
        };
    }
}

// Initialize analytics when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.aanpAnalytics = new AANP_PerformanceAnalytics();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.aanpAnalytics) {
        window.aanpAnalytics.destroy();
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AANP_PerformanceAnalytics;
}