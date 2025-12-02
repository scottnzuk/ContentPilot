"use strict";

function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _nonIterableRest(); }

function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance"); }

function _iterableToArrayLimit(arr, i) { if (!(Symbol.iterator in Object(arr) || Object.prototype.toString.call(arr) === "[object Arguments]")) { return; } var _arr = []; var _n = true; var _d = false; var _e = undefined; try { for (var _i = arr[Symbol.iterator](), _s; !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }

function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

/**
 * AI Auto News Poster Dashboard
 * Modern admin interface with real-time monitoring and analytics
 */
var AIDashboard =
/*#__PURE__*/
function () {
  function AIDashboard() {
    _classCallCheck(this, AIDashboard);

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


  _createClass(AIDashboard, [{
    key: "init",
    value: function init() {
      return regeneratorRuntime.async(function init$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              _context.prev = 0;
              // Show loading overlay
              this.showLoadingOverlay(); // Initialize components

              _context.next = 4;
              return regeneratorRuntime.awrap(this.initializeComponents());

            case 4:
              // Set up event listeners
              this.setupEventListeners(); // Initialize WebSocket connection

              _context.next = 7;
              return regeneratorRuntime.awrap(this.initializeWebSocket());

            case 7:
              _context.next = 9;
              return regeneratorRuntime.awrap(this.loadDashboardData());

            case 9:
              // Start real-time monitoring
              this.startRealTimeMonitoring(); // Hide loading overlay

              this.hideLoadingOverlay();
              console.log('Dashboard initialized successfully');
              _context.next = 18;
              break;

            case 14:
              _context.prev = 14;
              _context.t0 = _context["catch"](0);
              console.error('Dashboard initialization failed:', _context.t0);
              this.showNotification('Dashboard initialization failed', 'error');

            case 18:
            case "end":
              return _context.stop();
          }
        }
      }, null, this, [[0, 14]]);
    }
    /**
     * Initialize dashboard components
     */

  }, {
    key: "initializeComponents",
    value: function initializeComponents() {
      return regeneratorRuntime.async(function initializeComponents$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              // Initialize charts
              this.initializeCharts(); // Initialize metric counters

              this.initializeMetricCounters(); // Setup theme

              this.setupTheme(); // Setup accessibility

              this.setupAccessibility();

            case 4:
            case "end":
              return _context2.stop();
          }
        }
      }, null, this);
    }
    /**
     * Initialize all charts
     */

  }, {
    key: "initializeCharts",
    value: function initializeCharts() {
      // Performance chart
      var performanceCtx = document.getElementById('performance-chart');

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
      } // Activity chart


      var activityCtx = document.getElementById('activity-chart');

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
      } // Response time chart


      var responseTimeCtx = document.getElementById('response-time-chart');

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
      } // Memory usage chart


      var memoryCtx = document.getElementById('memory-usage-chart');

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
      } // CPU usage chart


      var cpuCtx = document.getElementById('cpu-usage-chart');

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

  }, {
    key: "initializeMetricCounters",
    value: function initializeMetricCounters() {
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

  }, {
    key: "setupEventListeners",
    value: function setupEventListeners() {
      var _this = this;

      // Navigation
      document.querySelectorAll('.sidebar-menu a').forEach(function (link) {
        link.addEventListener('click', function (e) {
          e.preventDefault();

          _this.switchSection(link.dataset.section);
        });
      }); // Refresh button

      var refreshBtn = document.getElementById('refresh-data');

      if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
          return _this.refreshData();
        });
      } // Start monitoring button


      var startMonitoringBtn = document.getElementById('start-monitoring');

      if (startMonitoringBtn) {
        startMonitoringBtn.addEventListener('click', function () {
          return _this.toggleMonitoring();
        });
      } // Time range selector


      var timeRangeSelect = document.getElementById('time-range');

      if (timeRangeSelect) {
        timeRangeSelect.addEventListener('change', function () {
          return _this.updateTimeRange(timeRangeSelect.value);
        });
      } // Chart controls


      document.querySelectorAll('.chart-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          return _this.switchChart(btn.dataset.chart);
        });
      }); // Notification button

      var notificationBtn = document.getElementById('notifications-btn');

      if (notificationBtn) {
        notificationBtn.addEventListener('click', function () {
          return _this.toggleNotifications();
        });
      } // SEO audit button


      var seoAuditBtn = document.getElementById('run-seo-audit');

      if (seoAuditBtn) {
        seoAuditBtn.addEventListener('click', function () {
          return _this.runSEOAudit();
        });
      } // API key generation


      var generateApiKeyBtn = document.getElementById('generate-api-key');

      if (generateApiKeyBtn) {
        generateApiKeyBtn.addEventListener('click', function () {
          return _this.generateAPIKey();
        });
      } // Settings


      var saveSettingsBtn = document.getElementById('save-settings');

      if (saveSettingsBtn) {
        saveSettingsBtn.addEventListener('click', function () {
          return _this.saveSettings();
        });
      } // Keyboard shortcuts


      document.addEventListener('keydown', function (e) {
        return _this.handleKeyboardShortcuts(e);
      });
    }
    /**
     * Initialize WebSocket connection for real-time updates
     */

  }, {
    key: "initializeWebSocket",
    value: function initializeWebSocket() {
      var _this2 = this;

      var protocol, wsUrl;
      return regeneratorRuntime.async(function initializeWebSocket$(_context3) {
        while (1) {
          switch (_context3.prev = _context3.next) {
            case 0:
              try {
                protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                wsUrl = "".concat(protocol, "//").concat(window.location.host, "/wp-admin/admin-ajax.php?action=ai_news_websocket");
                this.websocket = new WebSocket(wsUrl);

                this.websocket.onopen = function () {
                  _this2.isConnected = true;

                  _this2.updateConnectionStatus(true);

                  console.log('WebSocket connected');
                };

                this.websocket.onmessage = function (event) {
                  try {
                    var data = JSON.parse(event.data);

                    _this2.handleWebSocketMessage(data);
                  } catch (error) {
                    console.error('WebSocket message parsing error:', error);
                  }
                };

                this.websocket.onclose = function () {
                  _this2.isConnected = false;

                  _this2.updateConnectionStatus(false);

                  console.log('WebSocket disconnected'); // Attempt reconnection after 5 seconds

                  setTimeout(function () {
                    return _this2.initializeWebSocket();
                  }, 5000);
                };

                this.websocket.onerror = function (error) {
                  console.error('WebSocket error:', error);
                  _this2.isConnected = false;

                  _this2.updateConnectionStatus(false);
                };
              } catch (error) {
                console.error('WebSocket initialization failed:', error);
                this.isConnected = false;
                this.updateConnectionStatus(false);
              }

            case 1:
            case "end":
              return _context3.stop();
          }
        }
      }, null, this);
    }
    /**
     * Handle WebSocket messages
     */

  }, {
    key: "handleWebSocketMessage",
    value: function handleWebSocketMessage(data) {
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

  }, {
    key: "switchSection",
    value: function switchSection(sectionName) {
      // Update navigation
      document.querySelectorAll('.menu-item').forEach(function (item) {
        item.classList.remove('active');
      });
      document.querySelector("[data-section=\"".concat(sectionName, "\"]")).closest('.menu-item').classList.add('active'); // Update content sections

      document.querySelectorAll('.content-section').forEach(function (section) {
        section.classList.remove('active');
      });
      document.getElementById("".concat(sectionName, "-section")).classList.add('active'); // Load section-specific data

      this.loadSectionData(sectionName);
    }
    /**
     * Load data for specific section
     */

  }, {
    key: "loadSectionData",
    value: function loadSectionData(sectionName) {
      return regeneratorRuntime.async(function loadSectionData$(_context4) {
        while (1) {
          switch (_context4.prev = _context4.next) {
            case 0:
              _context4.prev = 0;
              _context4.t0 = sectionName;
              _context4.next = _context4.t0 === 'performance' ? 4 : _context4.t0 === 'seo' ? 7 : _context4.t0 === 'api' ? 10 : _context4.t0 === 'content' ? 13 : 16;
              break;

            case 4:
              _context4.next = 6;
              return regeneratorRuntime.awrap(this.loadPerformanceData());

            case 6:
              return _context4.abrupt("break", 16);

            case 7:
              _context4.next = 9;
              return regeneratorRuntime.awrap(this.loadSEOData());

            case 9:
              return _context4.abrupt("break", 16);

            case 10:
              _context4.next = 12;
              return regeneratorRuntime.awrap(this.loadAPIData());

            case 12:
              return _context4.abrupt("break", 16);

            case 13:
              _context4.next = 15;
              return regeneratorRuntime.awrap(this.loadContentData());

            case 15:
              return _context4.abrupt("break", 16);

            case 16:
              _context4.next = 22;
              break;

            case 18:
              _context4.prev = 18;
              _context4.t1 = _context4["catch"](0);
              console.error("Failed to load ".concat(sectionName, " data:"), _context4.t1);
              this.showNotification("Failed to load ".concat(sectionName, " data"), 'error');

            case 22:
            case "end":
              return _context4.stop();
          }
        }
      }, null, this, [[0, 18]]);
    }
    /**
     * Load initial dashboard data
     */

  }, {
    key: "loadDashboardData",
    value: function loadDashboardData() {
      var response, data;
      return regeneratorRuntime.async(function loadDashboardData$(_context5) {
        while (1) {
          switch (_context5.prev = _context5.next) {
            case 0:
              _context5.prev = 0;
              _context5.next = 3;
              return regeneratorRuntime.awrap(fetch(ajaxurl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'ai_news_get_dashboard_data',
                  nonce: ai_news_dashboard_nonce
                })
              }));

            case 3:
              response = _context5.sent;
              _context5.next = 6;
              return regeneratorRuntime.awrap(response.json());

            case 6:
              data = _context5.sent;

              if (!data.success) {
                _context5.next = 14;
                break;
              }

              this.updateMetrics(data.data.metrics);
              this.updateActivityList(data.data.activities);
              this.updatePerformanceCharts(data.data.performance);
              this.updateSEOData(data.data.seo);
              _context5.next = 15;
              break;

            case 14:
              throw new Error(data.data || 'Failed to load dashboard data');

            case 15:
              _context5.next = 21;
              break;

            case 17:
              _context5.prev = 17;
              _context5.t0 = _context5["catch"](0);
              console.error('Failed to load dashboard data:', _context5.t0);
              this.showNotification('Failed to load dashboard data', 'error');

            case 21:
            case "end":
              return _context5.stop();
          }
        }
      }, null, this, [[0, 17]]);
    }
    /**
     * Update metrics display
     */

  }, {
    key: "updateMetrics",
    value: function updateMetrics(metrics) {
      var _this3 = this;

      Object.entries(metrics).forEach(function (_ref) {
        var _ref2 = _slicedToArray(_ref, 2),
            key = _ref2[0],
            value = _ref2[1];

        if (_this3.metricsCounters[key]) {
          _this3.animateCounter(_this3.metricsCounters[key], value);
        }
      }); // Update percentage changes

      if (metrics.articles_change) {
        var changeElement = document.getElementById('articles-change');
        changeElement.textContent = "".concat(metrics.articles_change > 0 ? '+' : '').concat(metrics.articles_change, "%");
        changeElement.className = "metric-change ".concat(metrics.articles_change >= 0 ? 'positive' : 'negative');
      }

      if (metrics.views_change) {
        var _changeElement = document.getElementById('views-change');

        _changeElement.textContent = "".concat(metrics.views_change > 0 ? '+' : '').concat(metrics.views_change, "%");
        _changeElement.className = "metric-change ".concat(metrics.views_change >= 0 ? 'positive' : 'negative');
      }
    }
    /**
     * Animate counter from current to target value
     */

  }, {
    key: "animateCounter",
    value: function animateCounter(element, target) {
      var duration = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 1000;
      var start = parseFloat(element.textContent) || 0;
      var increment = (target - start) / (duration / 16);
      var current = start;
      var timer = setInterval(function () {
        current += increment;

        if (increment > 0 && current >= target || increment < 0 && current <= target) {
          current = target;
          clearInterval(timer);
        }

        element.textContent = Math.round(current).toLocaleString();
      }, 16);
    }
    /**
     * Update activity list
     */

  }, {
    key: "updateActivityList",
    value: function updateActivityList(activities) {
      var _this4 = this;

      var activityList = document.getElementById('activity-list');
      if (!activityList) return;
      activityList.innerHTML = activities.map(function (activity) {
        return "\n            <div class=\"activity-item\">\n                <div class=\"activity-icon ".concat(activity.type, "\">\n                    <i class=\"fas ").concat(activity.icon, "\"></i>\n                </div>\n                <div class=\"activity-content\">\n                    <div class=\"activity-title\">").concat(activity.title, "</div>\n                    <div class=\"activity-description\">").concat(activity.description, "</div>\n                    <div class=\"activity-time\">").concat(_this4.formatTimeAgo(activity.timestamp), "</div>\n                </div>\n            </div>\n        ");
      }).join('');
    }
    /**
     * Update chart data
     */

  }, {
    key: "updateChartData",
    value: function updateChartData(chartData) {
      if (this.charts[chartData.chartType]) {
        var chart = this.charts[chartData.chartType];
        chart.data.labels = chartData.labels;
        chart.data.datasets[0].data = chartData.data;
        chart.update('active');
      }
    }
    /**
     * Start real-time monitoring
     */

  }, {
    key: "startRealTimeMonitoring",
    value: function startRealTimeMonitoring() {
      var _this5 = this;

      this.monitoringInterval = setInterval(function () {
        _this5.fetchRealTimeData();
      }, this.updateInterval);
    }
    /**
     * Fetch real-time data
     */

  }, {
    key: "fetchRealTimeData",
    value: function fetchRealTimeData() {
      var response, data;
      return regeneratorRuntime.async(function fetchRealTimeData$(_context6) {
        while (1) {
          switch (_context6.prev = _context6.next) {
            case 0:
              _context6.prev = 0;
              _context6.next = 3;
              return regeneratorRuntime.awrap(fetch(ajaxurl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'ai_news_get_realtime_data',
                  nonce: ai_news_dashboard_nonce
                })
              }));

            case 3:
              response = _context6.sent;
              _context6.next = 6;
              return regeneratorRuntime.awrap(response.json());

            case 6:
              data = _context6.sent;

              if (data.success) {
                this.updateMetrics(data.data.metrics);
                this.updateRealTimeCharts(data.data.charts);
              }

              _context6.next = 13;
              break;

            case 10:
              _context6.prev = 10;
              _context6.t0 = _context6["catch"](0);
              console.error('Failed to fetch real-time data:', _context6.t0);

            case 13:
            case "end":
              return _context6.stop();
          }
        }
      }, null, this, [[0, 10]]);
    }
    /**
     * Update real-time charts
     */

  }, {
    key: "updateRealTimeCharts",
    value: function updateRealTimeCharts(chartData) {
      var _this6 = this;

      var now = new Date().toLocaleTimeString();
      Object.entries(chartData).forEach(function (_ref3) {
        var _ref4 = _slicedToArray(_ref3, 2),
            chartType = _ref4[0],
            value = _ref4[1];

        if (_this6.charts[chartType]) {
          var chart = _this6.charts[chartType]; // Add new data point

          chart.data.labels.push(now);
          chart.data.datasets[0].data.push(value); // Keep only last 20 data points

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

  }, {
    key: "handleAlert",
    value: function handleAlert(alert) {
      this.showNotification(alert.message, alert.severity);

      if (alert.severity === 'error') {
        this.addAlertToList(alert);
      }
    }
    /**
     * Add alert to performance alerts list
     */

  }, {
    key: "addAlertToList",
    value: function addAlertToList(alert) {
      var alertsList = document.getElementById('performance-alerts');
      if (!alertsList) return;
      var alertElement = document.createElement('div');
      alertElement.className = "alert-item ".concat(alert.severity);
      alertElement.innerHTML = "\n            <div class=\"alert-content\">\n                <div class=\"alert-title\">".concat(alert.title, "</div>\n                <div class=\"alert-message\">").concat(alert.message, "</div>\n            </div>\n        ");
      alertsList.insertBefore(alertElement, alertsList.firstChild); // Remove alerts older than 10 items

      while (alertsList.children.length > 10) {
        alertsList.removeChild(alertsList.lastChild);
      }
    }
    /**
     * Update SEO data
     */

  }, {
    key: "updateSEOData",
    value: function updateSEOData(seoData) {
      var _this7 = this;

      // Update overall score
      var overallScoreElement = document.getElementById('overall-seo-score');

      if (overallScoreElement) {
        var scoreCircle = overallScoreElement;
        var percentage = seoData.overall_score;
        scoreCircle.style.background = "conic-gradient(#007cba 0deg, #007cba ".concat(percentage * 3.6, "deg, #e5e7eb ").concat(percentage * 3.6, "deg)");
        var scoreValue = scoreCircle.querySelector('.score-value');

        if (scoreValue) {
          this.animateCounter(scoreValue, percentage);
        }
      } // Update individual scores


      ['content-quality', 'eeat', 'technical-seo'].forEach(function (metric) {
        var element = document.getElementById("".concat(metric, "-score"));

        if (element && seoData[metric]) {
          _this7.animateCounter(element, seoData[metric]);
        }
      }); // Update status

      var seoStatus = document.getElementById('seo-status');

      if (seoStatus) {
        var score = seoData.overall_score;
        var status = 'Loading...';
        var statusClass = '';

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
        seoStatus.className = "metric-status ".concat(statusClass);
      } // Update recommendations


      this.updateSEORecommendations(seoData.recommendations || []);
    }
    /**
     * Update SEO recommendations
     */

  }, {
    key: "updateSEORecommendations",
    value: function updateSEORecommendations(recommendations) {
      var recommendationsList = document.getElementById('seo-recommendations');
      if (!recommendationsList) return;
      recommendationsList.innerHTML = recommendations.map(function (rec) {
        return "\n            <div class=\"recommendation-item\">\n                <div class=\"recommendation-title\">".concat(rec.title, "</div>\n                <div class=\"recommendation-description\">").concat(rec.description, "</div>\n            </div>\n        ");
      }).join('');
    }
    /**
     * Run SEO audit
     */

  }, {
    key: "runSEOAudit",
    value: function runSEOAudit() {
      var button, originalText, response, data;
      return regeneratorRuntime.async(function runSEOAudit$(_context7) {
        while (1) {
          switch (_context7.prev = _context7.next) {
            case 0:
              button = document.getElementById('run-seo-audit');

              if (button) {
                _context7.next = 3;
                break;
              }

              return _context7.abrupt("return");

            case 3:
              originalText = button.innerHTML;
              button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Audit...';
              button.disabled = true;
              _context7.prev = 6;
              _context7.next = 9;
              return regeneratorRuntime.awrap(fetch(ajaxurl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'ai_news_run_seo_audit',
                  nonce: ai_news_dashboard_nonce
                })
              }));

            case 9:
              response = _context7.sent;
              _context7.next = 12;
              return regeneratorRuntime.awrap(response.json());

            case 12:
              data = _context7.sent;

              if (!data.success) {
                _context7.next = 18;
                break;
              }

              this.updateSEOData(data.data);
              this.showNotification('SEO audit completed successfully', 'success');
              _context7.next = 19;
              break;

            case 18:
              throw new Error(data.data || 'SEO audit failed');

            case 19:
              _context7.next = 25;
              break;

            case 21:
              _context7.prev = 21;
              _context7.t0 = _context7["catch"](6);
              console.error('SEO audit failed:', _context7.t0);
              this.showNotification('SEO audit failed', 'error');

            case 25:
              _context7.prev = 25;
              button.innerHTML = originalText;
              button.disabled = false;
              return _context7.finish(25);

            case 29:
            case "end":
              return _context7.stop();
          }
        }
      }, null, this, [[6, 21, 25, 29]]);
    }
    /**
     * Generate API key
     */

  }, {
    key: "generateAPIKey",
    value: function generateAPIKey() {
      var button, originalText, response, data;
      return regeneratorRuntime.async(function generateAPIKey$(_context8) {
        while (1) {
          switch (_context8.prev = _context8.next) {
            case 0:
              button = document.getElementById('generate-api-key');

              if (button) {
                _context8.next = 3;
                break;
              }

              return _context8.abrupt("return");

            case 3:
              originalText = button.innerHTML;
              button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
              button.disabled = true;
              _context8.prev = 6;
              _context8.next = 9;
              return regeneratorRuntime.awrap(fetch(ajaxurl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'ai_news_generate_api_key',
                  nonce: ai_news_dashboard_nonce
                })
              }));

            case 9:
              response = _context8.sent;
              _context8.next = 12;
              return regeneratorRuntime.awrap(response.json());

            case 12:
              data = _context8.sent;

              if (!data.success) {
                _context8.next = 18;
                break;
              }

              this.showNotification('API key generated successfully', 'success'); // You might want to display the key in a modal or copy it to clipboard

              this.copyToClipboard(data.data.api_key);
              _context8.next = 19;
              break;

            case 18:
              throw new Error(data.data || 'Failed to generate API key');

            case 19:
              _context8.next = 25;
              break;

            case 21:
              _context8.prev = 21;
              _context8.t0 = _context8["catch"](6);
              console.error('API key generation failed:', _context8.t0);
              this.showNotification('Failed to generate API key', 'error');

            case 25:
              _context8.prev = 25;
              button.innerHTML = originalText;
              button.disabled = false;
              return _context8.finish(25);

            case 29:
            case "end":
              return _context8.stop();
          }
        }
      }, null, this, [[6, 21, 25, 29]]);
    }
    /**
     * Copy text to clipboard
     */

  }, {
    key: "copyToClipboard",
    value: function copyToClipboard(text) {
      return regeneratorRuntime.async(function copyToClipboard$(_context9) {
        while (1) {
          switch (_context9.prev = _context9.next) {
            case 0:
              _context9.prev = 0;
              _context9.next = 3;
              return regeneratorRuntime.awrap(navigator.clipboard.writeText(text));

            case 3:
              this.showNotification('Copied to clipboard', 'info');
              _context9.next = 9;
              break;

            case 6:
              _context9.prev = 6;
              _context9.t0 = _context9["catch"](0);
              console.error('Failed to copy to clipboard:', _context9.t0);

            case 9:
            case "end":
              return _context9.stop();
          }
        }
      }, null, this, [[0, 6]]);
    }
    /**
     * Refresh data
     */

  }, {
    key: "refreshData",
    value: function refreshData() {
      return regeneratorRuntime.async(function refreshData$(_context10) {
        while (1) {
          switch (_context10.prev = _context10.next) {
            case 0:
              this.showLoadingOverlay();
              _context10.next = 3;
              return regeneratorRuntime.awrap(this.loadDashboardData());

            case 3:
              this.hideLoadingOverlay();
              this.showNotification('Data refreshed successfully', 'success');

            case 5:
            case "end":
              return _context10.stop();
          }
        }
      }, null, this);
    }
    /**
     * Toggle monitoring
     */

  }, {
    key: "toggleMonitoring",
    value: function toggleMonitoring() {
      var button = document.getElementById('start-monitoring');
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

  }, {
    key: "updateConnectionStatus",
    value: function updateConnectionStatus(connected) {
      var statusIndicator = document.getElementById('connection-status');
      var statusText = document.querySelector('.status-text');

      if (statusIndicator) {
        statusIndicator.className = "status-indicator ".concat(connected ? 'online' : 'offline');
      }

      if (statusText) {
        statusText.textContent = connected ? 'Connected' : 'Disconnected';
      }
    }
    /**
     * Show notification
     */

  }, {
    key: "showNotification",
    value: function showNotification(message) {
      var type = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'info';
      // Create notification element
      var notification = document.createElement('div');
      notification.className = "notification ".concat(type);
      notification.innerHTML = "\n            <div class=\"notification-content\">\n                <span>".concat(message, "</span>\n                <button class=\"notification-close\">&times;</button>\n            </div>\n        "); // Add to notifications array for the dropdown

      this.notifications.unshift({
        id: Date.now(),
        message: message,
        type: type,
        timestamp: new Date(),
        read: false
      }); // Update notification count

      this.updateNotificationCount(); // Add to page

      document.body.appendChild(notification); // Auto remove after 5 seconds

      setTimeout(function () {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 5000); // Close button functionality

      var closeBtn = notification.querySelector('.notification-close');
      closeBtn.addEventListener('click', function () {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      });
    }
    /**
     * Update notification count
     */

  }, {
    key: "updateNotificationCount",
    value: function updateNotificationCount() {
      var countElement = document.getElementById('notification-count');
      var unreadCount = this.notifications.filter(function (n) {
        return !n.read;
      }).length;

      if (countElement) {
        countElement.textContent = unreadCount;
        countElement.style.display = unreadCount > 0 ? 'block' : 'none';
      }
    }
    /**
     * Toggle notifications dropdown
     */

  }, {
    key: "toggleNotifications",
    value: function toggleNotifications() {
      var panel = document.getElementById('notification-panel');
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

  }, {
    key: "renderNotificationsDropdown",
    value: function renderNotificationsDropdown() {
      var _this8 = this;

      var panel = document.getElementById('notification-panel');
      if (!panel) return;
      var recentNotifications = this.notifications.slice(0, 10);
      panel.innerHTML = "\n            <div class=\"notification-panel-header\">\n                <h4>Notifications</h4>\n                <button class=\"mark-all-read\">Mark all read</button>\n            </div>\n            <div class=\"notification-list\">\n                ".concat(recentNotifications.map(function (notification) {
        return "\n                    <div class=\"notification-item ".concat(notification.read ? 'read' : 'unread', "\">\n                        <div class=\"notification-message\">").concat(notification.message, "</div>\n                        <div class=\"notification-time\">").concat(_this8.formatTimeAgo(notification.timestamp), "</div>\n                    </div>\n                ");
      }).join(''), "\n                ").concat(recentNotifications.length === 0 ? '<div class="no-notifications">No notifications</div>' : '', "\n            </div>\n        "); // Mark all read button

      var markAllReadBtn = panel.querySelector('.mark-all-read');

      if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function () {
          _this8.notifications.forEach(function (n) {
            return n.read = true;
          });

          _this8.updateNotificationCount();

          _this8.renderNotificationsDropdown();
        });
      }
    }
    /**
     * Handle keyboard shortcuts
     */

  }, {
    key: "handleKeyboardShortcuts",
    value: function handleKeyboardShortcuts(e) {
      // Ctrl/Cmd + R: Refresh data
      if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
        e.preventDefault();
        this.refreshData();
      } // Ctrl/Cmd + 1-6: Switch sections


      if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '6') {
        e.preventDefault();
        var sections = ['overview', 'performance', 'content', 'seo', 'api', 'settings'];
        var sectionIndex = parseInt(e.key) - 1;

        if (sections[sectionIndex]) {
          this.switchSection(sections[sectionIndex]);
        }
      }
    }
    /**
     * Setup theme
     */

  }, {
    key: "setupTheme",
    value: function setupTheme() {
      var _this9 = this;

      var themeSelect = document.getElementById('theme-select');

      if (themeSelect) {
        // Load saved theme
        var savedTheme = localStorage.getItem('dashboard-theme') || 'light';
        themeSelect.value = savedTheme;
        this.applyTheme(savedTheme);
        themeSelect.addEventListener('change', function (e) {
          _this9.applyTheme(e.target.value);

          localStorage.setItem('dashboard-theme', e.target.value);
        });
      }
    }
    /**
     * Apply theme
     */

  }, {
    key: "applyTheme",
    value: function applyTheme(theme) {
      document.documentElement.setAttribute('data-theme', theme);
    }
    /**
     * Setup accessibility features
     */

  }, {
    key: "setupAccessibility",
    value: function setupAccessibility() {
      // Add skip link
      var skipLink = document.createElement('a');
      skipLink.href = '#main-content';
      skipLink.textContent = 'Skip to main content';
      skipLink.className = 'skip-link';
      document.body.insertBefore(skipLink, document.body.firstChild); // Add main content id

      var dashboardMain = document.querySelector('.dashboard-main');

      if (dashboardMain) {
        dashboardMain.id = 'main-content';
      } // Improve focus management


      document.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
          document.body.classList.add('keyboard-navigation');
        }
      });
      document.addEventListener('mousedown', function () {
        document.body.classList.remove('keyboard-navigation');
      });
    }
    /**
     * Show loading overlay
     */

  }, {
    key: "showLoadingOverlay",
    value: function showLoadingOverlay() {
      var overlay = document.getElementById('loading-overlay');

      if (overlay) {
        overlay.style.display = 'flex';
      }
    }
    /**
     * Hide loading overlay
     */

  }, {
    key: "hideLoadingOverlay",
    value: function hideLoadingOverlay() {
      var overlay = document.getElementById('loading-overlay');

      if (overlay) {
        overlay.style.display = 'none';
      }
    }
    /**
     * Format time ago
     */

  }, {
    key: "formatTimeAgo",
    value: function formatTimeAgo(date) {
      var now = new Date();
      var diff = now - new Date(date);
      var minutes = Math.floor(diff / 60000);
      var hours = Math.floor(diff / 3600000);
      var days = Math.floor(diff / 86400000);
      if (minutes < 1) return 'Just now';
      if (minutes < 60) return "".concat(minutes, " minute").concat(minutes > 1 ? 's' : '', " ago");
      if (hours < 24) return "".concat(hours, " hour").concat(hours > 1 ? 's' : '', " ago");
      return "".concat(days, " day").concat(days > 1 ? 's' : '', " ago");
    }
    /**
     * Utility method to format numbers
     */

  }, {
    key: "formatNumber",
    value: function formatNumber(num) {
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

  }, {
    key: "destroy",
    value: function destroy() {
      // Clear intervals
      if (this.monitoringInterval) {
        clearInterval(this.monitoringInterval);
      } // Close WebSocket


      if (this.websocket) {
        this.websocket.close();
      } // Destroy charts


      Object.values(this.charts).forEach(function (chart) {
        if (chart && typeof chart.destroy === 'function') {
          chart.destroy();
        }
      });
    }
  }]);

  return AIDashboard;
}(); // Initialize dashboard when DOM is ready


document.addEventListener('DOMContentLoaded', function () {
  window.aiDashboard = new AIDashboard();
}); // Handle page visibility changes

document.addEventListener('visibilitychange', function () {
  if (window.aiDashboard) {
    if (document.hidden) {
      // Page is hidden, reduce update frequency
      if (window.aiDashboard.monitoringInterval) {
        clearInterval(window.aiDashboard.monitoringInterval);
        window.aiDashboard.monitoringInterval = setInterval(function () {
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
}); // Cleanup on page unload

window.addEventListener('beforeunload', function () {
  if (window.aiDashboard) {
    window.aiDashboard.destroy();
  }
}); // Export for potential module usage

if (typeof module !== 'undefined' && module.exports) {
  module.exports = AIDashboard;
}