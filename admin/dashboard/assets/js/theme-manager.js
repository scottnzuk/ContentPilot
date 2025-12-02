/**
 * Theme Manager for AI Auto News Poster Dashboard
 * 
 * Handles dark/light theme switching, system preference detection,
 * and smooth theme transitions with persistence.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

class AANP_ThemeManager {
    
    /**
     * Theme states
     */
    private currentTheme = 'light';
    private systemTheme = 'light';
    private userPreference = null;
    
    /**
     * Theme configuration
     */
    private config = {
        storageKey: 'aanp-theme-preference',
        transitionDuration: 300,
        storageKey: 'aanp-theme-preference',
        respectSystemPreference: true,
        animateTransitions: true
    };
    
    /**
     * Theme CSS variables
     */
    private themes = {
        light: {
            // Colors
            '--bg-primary': '#ffffff',
            '--bg-secondary': '#f8f9fa',
            '--bg-tertiary': '#e9ecef',
            '--text-primary': '#212529',
            '--text-secondary': '#6c757d',
            '--text-muted': '#adb5bd',
            
            // Glassmorphism
            '--glass-white': 'rgba(255, 255, 255, 0.25)',
            '--glass-white-strong': 'rgba(255, 255, 255, 0.4)',
            '--glass-dark': 'rgba(0, 0, 0, 0.05)',
            '--glass-dark-strong': 'rgba(0, 0, 0, 0.1)',
            '--glass-border': 'rgba(255, 255, 255, 0.18)',
            '--glass-border-light': 'rgba(255, 255, 255, 0.12)',
            
            // Gradients
            '--gradient-primary': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            '--gradient-secondary': 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            '--gradient-accent': 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            '--gradient-success': 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            '--gradient-warning': 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            '--gradient-dark': 'linear-gradient(135deg, #2c3e50 0%, #3498db 100%)',
            
            // Shadows
            '--shadow-sm': '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
            '--shadow': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
            '--shadow-md': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
            '--shadow-lg': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
            '--shadow-xl': '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
            '--shadow-glass': '0 8px 32px rgba(31, 38, 135, 0.37)',
            '--shadow-glass-subtle': '0 4px 16px rgba(31, 38, 135, 0.2)',
            '--shadow-glow': '0 0 20px rgba(0, 124, 186, 0.3)',
            '--shadow-glow-colored': '0 0 30px rgba(102, 126, 234, 0.4)',
            
            // Interactive States
            '--hover-bg': '#f1f3f4',
            '--active-bg': '#e8eaed',
            '--focus-ring': '0 0 0 3px rgba(0, 124, 186, 0.1)',
            
            // Status Colors
            '--success-bg': '#d4edda',
            '--success-border': '#c3e6cb',
            '--warning-bg': '#fff3cd',
            '--warning-border': '#ffeaa7',
            '--error-bg': '#f8d7da',
            '--error-border': '#f5c6cb',
            '--info-bg': '#d1ecf1',
            '--info-border': '#bee5eb'
        },
        
        dark: {
            // Colors
            '--bg-primary': '#1a1a1a',
            '--bg-secondary': '#2d2d2d',
            '--bg-tertiary': '#404040',
            '--text-primary': '#ffffff',
            '--text-secondary': '#b3b3b3',
            '--text-muted': '#666666',
            
            // Glassmorphism
            '--glass-white': 'rgba(255, 255, 255, 0.1)',
            '--glass-white-strong': 'rgba(255, 255, 255, 0.15)',
            '--glass-dark': 'rgba(0, 0, 0, 0.2)',
            '--glass-dark-strong': 'rgba(0, 0, 0, 0.4)',
            '--glass-border': 'rgba(255, 255, 255, 0.1)',
            '--glass-border-light': 'rgba(255, 255, 255, 0.05)',
            
            // Gradients
            '--gradient-primary': 'linear-gradient(135deg, #4c63d2 0%, #6c5ce7 100%)',
            '--gradient-secondary': 'linear-gradient(135deg, #fd79a8 0%, #e84393 100%)',
            '--gradient-accent': 'linear-gradient(135deg, #74b9ff 0%, #0984e3 100%)',
            '--gradient-success': 'linear-gradient(135deg, #00b894 0%, #00cec9 100%)',
            '--gradient-warning': 'linear-gradient(135deg, #fdcb6e 0%, #e17055 100%)',
            '--gradient-dark': 'linear-gradient(135deg, #2d3436 0%, #636e72 100%)',
            
            // Shadows
            '--shadow-sm': '0 1px 2px 0 rgba(0, 0, 0, 0.3)',
            '--shadow': '0 1px 3px 0 rgba(0, 0, 0, 0.4), 0 1px 2px 0 rgba(0, 0, 0, 0.3)',
            '--shadow-md': '0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -1px rgba(0, 0, 0, 0.3)',
            '--shadow-lg': '0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.3)',
            '--shadow-xl': '0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.2)',
            '--shadow-glass': '0 8px 32px rgba(0, 0, 0, 0.5)',
            '--shadow-glass-subtle': '0 4px 16px rgba(0, 0, 0, 0.3)',
            '--shadow-glow': '0 0 20px rgba(74, 99, 210, 0.5)',
            '--shadow-glow-colored': '0 0 30px rgba(76, 99, 210, 0.6)',
            
            // Interactive States
            '--hover-bg': '#363636',
            '--active-bg': '#404040',
            '--focus-ring': '0 0 0 3px rgba(74, 99, 210, 0.3)',
            
            // Status Colors
            '--success-bg': '#1e3a1e',
            '--success-border': '#2d5a2d',
            '--warning-bg': '#3d3a1e',
            '--warning-border': '#5a5a2d',
            '--error-bg': '#3a1e1e',
            '--error-border': '#5a2d2d',
            '--info-bg': '#1e3a3a',
            '--info-border': '#2d5a5a'
        }
    };
    
    /**
     * DOM elements
     */
    private toggleButton = null;
    private toggleIcon = null;
    
    constructor() {
        this.init();
    }
    
    /**
     * Initialize theme manager
     */
    async init() {
        try {
            // Detect system preference
            this.detectSystemPreference();
            
            // Load user preference
            this.loadUserPreference();
            
            // Determine initial theme
            this.determineInitialTheme();
            
            // Apply initial theme
            await this.applyTheme(this.currentTheme);
            
            // Setup UI
            this.setupThemeToggle();
            
            // Setup system preference listener
            this.setupSystemPreferenceListener();
            
            // Setup storage listener
            this.setupStorageListener();
            
            // Setup keyboard shortcuts
            this.setupKeyboardShortcuts();
            
            console.log('Theme Manager initialized:', this.currentTheme);
            
        } catch (error) {
            console.error('Failed to initialize Theme Manager:', error);
        }
    }
    
    /**
     * Detect system color scheme preference
     */
    detectSystemPreference() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.systemTheme = 'dark';
        } else {
            this.systemTheme = 'light';
        }
        
        console.log('System theme preference:', this.systemTheme);
    }
    
    /**
     * Load user theme preference from localStorage
     */
    loadUserPreference() {
        try {
            const saved = localStorage.getItem(this.config.storageKey);
            if (saved === 'light' || saved === 'dark') {
                this.userPreference = saved;
                console.log('Loaded user preference:', this.userPreference);
            } else {
                this.userPreference = null;
            }
        } catch (error) {
            console.warn('Failed to load theme preference:', error);
            this.userPreference = null;
        }
    }
    
    /**
     * Determine initial theme based on preferences
     */
    determineInitialTheme() {
        if (this.userPreference) {
            this.currentTheme = this.userPreference;
        } else if (this.config.respectSystemPreference) {
            this.currentTheme = this.systemTheme;
        } else {
            this.currentTheme = 'light';
        }
        
        console.log('Initial theme determined:', this.currentTheme);
    }
    
    /**
     * Apply theme with smooth transitions
     */
    async applyTheme(theme) {
        if (!this.themes[theme]) {
            console.error('Invalid theme:', theme);
            return;
        }
        
        const previousTheme = this.currentTheme;
        this.currentTheme = theme;
        
        try {
            // Add transition class
            if (this.config.animateTransitions) {
                document.body.classList.add('theme-transitioning');
            }
            
            // Apply CSS variables
            this.applyThemeVariables(theme);
            
            // Update document attributes
            this.updateDocumentAttributes(theme);
            
            // Update charts and visualizations
            this.updateVisualizations(theme);
            
            // Save user preference if not following system
            if (this.userPreference !== null) {
                this.saveUserPreference(theme);
            }
            
            // Update toggle UI
            this.updateToggleUI(theme);
            
            // Dispatch theme change event
            this.dispatchThemeChangeEvent(previousTheme, theme);
            
            // Wait for transitions to complete
            if (this.config.animateTransitions) {
                await this.delay(this.config.transitionDuration);
                document.body.classList.remove('theme-transitioning');
            }
            
            console.log(`Theme changed from ${previousTheme} to ${theme}`);
            
        } catch (error) {
            console.error('Failed to apply theme:', error);
            // Revert on error
            this.currentTheme = previousTheme;
        }
    }
    
    /**
     * Apply CSS variables for theme
     */
    applyThemeVariables(theme) {
        const root = document.documentElement;
        const variables = this.themes[theme];
        
        Object.entries(variables).forEach(([property, value]) => {
            root.style.setProperty(property, value);
        });
    }
    
    /**
     * Update document attributes
     */
    updateDocumentAttributes(theme) {
        // Update data-theme attribute
        document.documentElement.setAttribute('data-theme', theme);
        
        // Update meta theme-color for mobile browsers
        this.updateMetaThemeColor(theme);
        
        // Update body class
        document.body.classList.toggle('dark-theme', theme === 'dark');
        document.body.classList.toggle('light-theme', theme === 'light');
    }
    
    /**
     * Update meta theme-color for mobile browsers
     */
    updateMetaThemeColor(theme) {
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }
        
        const colors = {
            light: '#ffffff',
            dark: '#1a1a1a'
        };
        
        metaThemeColor.content = colors[theme];
    }
    
    /**
     * Update charts and visualizations for theme change
     */
    updateVisualizations(theme) {
        // Update Chart.js charts if they exist
        if (window.aanpAnalytics && window.aanpAnalytics.charts) {
            window.aanpAnalytics.charts.forEach((chart) => {
                this.updateChartTheme(chart, theme);
            });
        }
        
        // Update any other visualizations
        this.updateCustomElements(theme);
    }
    
    /**
     * Update Chart.js chart theme
     */
    updateChartTheme(chart, theme) {
        const isDark = theme === 'dark';
        const textColor = isDark ? '#ffffff' : '#212529';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        
        // Update scales
        if (chart.options.scales) {
            Object.keys(chart.options.scales).forEach(scaleKey => {
                const scale = chart.options.scales[scaleKey];
                if (scale.grid) {
                    scale.grid.color = gridColor;
                }
                if (scale.ticks) {
                    scale.ticks.color = textColor;
                }
            });
        }
        
        // Update tooltip styles
        if (chart.options.plugins && chart.options.plugins.tooltip) {
            const tooltip = chart.options.plugins.tooltip;
            tooltip.backgroundColor = isDark ? 'rgba(0, 0, 0, 0.8)' : 'rgba(255, 255, 255, 0.9)';
            tooltip.titleColor = textColor;
            tooltip.bodyColor = textColor;
        }
        
        chart.update('none');
    }
    
    /**
     * Update custom elements for theme
     */
    updateCustomElements(theme) {
        // Update code syntax highlighting
        this.updateSyntaxHighlighting(theme);
        
        // Update progress bars and other components
        this.updateProgressBars(theme);
        
        // Update tooltips
        this.updateTooltips(theme);
    }
    
    /**
     * Update syntax highlighting theme
     */
    updateSyntaxHighlighting(theme) {
        // Dispatch event for syntax highlighters to listen to
        document.dispatchEvent(new CustomEvent('themeChange', {
            detail: { theme, previousTheme: this.currentTheme }
        }));
    }
    
    /**
     * Update progress bars
     */
    updateProgressBars(theme) {
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
            bar.style.backgroundColor = `var(--gradient-primary)`;
        });
    }
    
    /**
     * Update tooltips
     */
    updateTooltips(theme) {
        const tooltips = document.querySelectorAll('[data-tooltip]');
        tooltips.forEach(tooltip => {
            tooltip.style.setProperty('--tooltip-bg', 
                theme === 'dark' ? 'rgba(0, 0, 0, 0.9)' : 'rgba(0, 0, 0, 0.8)');
        });
    }
    
    /**
     * Setup theme toggle button
     */
    setupThemeToggle() {
        // Create toggle button if it doesn't exist
        this.createToggleButton();
        
        // Update initial UI state
        this.updateToggleUI(this.currentTheme);
    }
    
    /**
     * Create theme toggle button
     */
    createToggleButton() {
        // Check if button already exists
        this.toggleButton = document.querySelector('.theme-toggle');
        
        if (!this.toggleButton) {
            // Find appropriate location for the toggle
            const headerActions = document.querySelector('.header-actions') || 
                                document.querySelector('.dashboard-header');
            
            if (headerActions) {
                this.toggleButton = document.createElement('button');
                this.toggleButton.className = 'theme-toggle btn btn-outline';
                this.toggleButton.setAttribute('aria-label', 'Toggle theme');
                this.toggleButton.setAttribute('title', 'Toggle dark/light theme');
                
                this.toggleButton.innerHTML = `
                    <i class="theme-icon fas fa-sun"></i>
                    <span class="theme-text sr-only">Toggle Theme</span>
                `;
                
                // Add styles
                this.toggleButton.style.cssText = `
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 40px;
                    height: 40px;
                    border-radius: var(--border-radius);
                    background: var(--glass-white);
                    backdrop-filter: var(--backdrop-blur);
                    border: 1px solid var(--glass-border);
                    transition: all 0.3s ease;
                    cursor: pointer;
                `;
                
                // Insert toggle button
                headerActions.appendChild(this.toggleButton);
                
                // Add click event listener
                this.toggleButton.addEventListener('click', () => {
                    this.toggleTheme();
                });
                
                // Add hover effects
                this.toggleButton.addEventListener('mouseenter', () => {
                    this.toggleButton.style.transform = 'scale(1.05)';
                    this.toggleButton.style.boxShadow = 'var(--shadow-glow)';
                });
                
                this.toggleButton.addEventListener('mouseleave', () => {
                    this.toggleButton.style.transform = 'scale(1)';
                    this.toggleButton.style.boxShadow = '';
                });
            }
        }
        
        this.toggleIcon = this.toggleButton?.querySelector('.theme-icon');
    }
    
    /**
     * Update toggle button UI
     */
    updateToggleUI(theme) {
        if (!this.toggleButton || !this.toggleIcon) return;
        
        // Update icon
        const iconClass = theme === 'dark' ? 'fa-sun' : 'fa-moon';
        this.toggleIcon.className = `theme-icon fas ${iconClass}`;
        
        // Update title
        const title = theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme';
        this.toggleButton.setAttribute('title', title);
        
        // Update aria-label
        this.toggleButton.setAttribute('aria-label', title);
        
        // Update button appearance
        this.updateToggleAppearance(theme);
    }
    
    /**
     * Update toggle button appearance
     */
    updateToggleAppearance(theme) {
        if (!this.toggleButton) return;
        
        const isDark = theme === 'dark';
        this.toggleButton.classList.toggle('dark', isDark);
        this.toggleButton.classList.toggle('light', !isDark);
        
        // Add animation class
        this.toggleButton.classList.add('theme-toggle-animating');
        setTimeout(() => {
            this.toggleButton.classList.remove('theme-toggle-animating');
        }, 300);
    }
    
    /**
     * Toggle between themes
     */
    async toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        
        try {
            // Add loading state
            this.toggleButton.classList.add('loading');
            
            await this.applyTheme(newTheme);
            
            // Clear loading state
            this.toggleButton.classList.remove('loading');
            
        } catch (error) {
            console.error('Failed to toggle theme:', error);
            this.toggleButton.classList.remove('loading');
        }
    }
    
    /**
     * Setup system preference change listener
     */
    setupSystemPreferenceListener() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            mediaQuery.addListener((e) => {
                const newSystemTheme = e.matches ? 'dark' : 'light';
                
                if (this.systemTheme !== newSystemTheme) {
                    this.systemTheme = newSystemTheme;
                    
                    // Only auto-switch if user hasn't set a preference
                    if (!this.userPreference && this.config.respectSystemPreference) {
                        console.log('Auto-switching to system preference:', newSystemTheme);
                        this.applyTheme(newSystemTheme);
                    }
                }
            });
        }
    }
    
    /**
     * Setup storage listener for multi-tab synchronization
     */
    setupStorageListener() {
        window.addEventListener('storage', (e) => {
            if (e.key === this.config.storageKey && e.newValue !== null) {
                const newTheme = e.newValue;
                if (newTheme !== this.currentTheme) {
                    console.log('Theme changed in another tab:', newTheme);
                    this.applyTheme(newTheme);
                }
            }
        });
    }
    
    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Shift + T for theme toggle
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }
    
    /**
     * Save user preference
     */
    saveUserPreference(theme) {
        try {
            localStorage.setItem(this.config.storageKey, theme);
            console.log('Saved theme preference:', theme);
        } catch (error) {
            console.warn('Failed to save theme preference:', error);
        }
    }
    
    /**
     * Set theme explicitly (for external use)
     */
    async setTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') {
            console.error('Invalid theme:', theme);
            return;
        }
        
        await this.applyTheme(theme);
        
        // Update user preference
        this.userPreference = theme;
        this.saveUserPreference(theme);
    }
    
    /**
     * Reset to system preference
     */
    async resetToSystemPreference() {
        this.userPreference = null;
        
        try {
            localStorage.removeItem(this.config.storageKey);
        } catch (error) {
            console.warn('Failed to remove theme preference:', error);
        }
        
        await this.applyTheme(this.systemTheme);
    }
    
    /**
     * Dispatch theme change event
     */
    dispatchThemeChangeEvent(previousTheme, newTheme) {
        const event = new CustomEvent('themeChanged', {
            detail: {
                previousTheme,
                newTheme,
                systemTheme: this.systemTheme,
                userPreference: this.userPreference
            }
        });
        
        document.dispatchEvent(event);
    }
    
    /**
     * Get current theme
     */
    getCurrentTheme() {
        return this.currentTheme;
    }
    
    /**
     * Get system theme
     */
    getSystemTheme() {
        return this.systemTheme;
    }
    
    /**
     * Get user preference
     */
    getUserPreference() {
        return this.userPreference;
    }
    
    /**
     * Check if theme is dark
     */
    isDarkTheme() {
        return this.currentTheme === 'dark';
    }
    
    /**
     * Check if theme is light
     */
    isLightTheme() {
        return this.currentTheme === 'light';
    }
    
    /**
     * Get theme state
     */
    getState() {
        return {
            currentTheme: this.currentTheme,
            systemTheme: this.systemTheme,
            userPreference: this.userPreference,
            respectsSystemPreference: this.config.respectSystemPreference,
            animateTransitions: this.config.animateTransitions
        };
    }
    
    /**
     * Utility delay function
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    /**
     * Destroy theme manager
     */
    destroy() {
        // Remove event listeners
        document.removeEventListener('keydown', this.handleKeydown);
        
        // Remove toggle button if we created it
        if (this.toggleButton && this.toggleButton.parentNode) {
            this.toggleButton.parentNode.removeChild(this.toggleButton);
        }
        
        console.log('Theme Manager destroyed');
    }
}

// Add theme transition CSS
const themeTransitionCSS = `
    .theme-transitioning * {
        transition: background-color ${AANP_ThemeManager.prototype.config?.transitionDuration || 300}ms ease,
                   color ${AANP_ThemeManager.prototype.config?.transitionDuration || 300}ms ease,
                   border-color ${AANP_ThemeManager.prototype.config?.transitionDuration || 300}ms ease,
                   box-shadow ${AANP_ThemeManager.prototype.config?.transitionDuration || 300}ms ease !important;
    }
    
    .theme-toggle-animating {
        animation: themeToggleRotate 0.3s ease-in-out;
    }
    
    @keyframes themeToggleRotate {
        0% { transform: rotate(0deg); }
        50% { transform: rotate(180deg); }
        100% { transform: rotate(360deg); }
    }
    
    .theme-toggle.loading {
        opacity: 0.7;
        pointer-events: none;
    }
    
    .theme-toggle.loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Theme-specific enhancements */
    .dark-theme .code-block {
        background: #2d3748;
        border-color: #4a5568;
    }
    
    .light-theme .code-block {
        background: #f7fafc;
        border-color: #e2e8f0;
    }
    
    /* Smooth scrolling for theme transitions */
    .theme-transitioning {
        scroll-behavior: smooth;
    }
    
    /* High contrast mode support */
    @media (prefers-contrast: high) {
        .theme-transitioning * {
            transition: none !important;
        }
    }
`;

// Add CSS to document
if (typeof document !== 'undefined') {
    const style = document.createElement('style');
    style.textContent = themeTransitionCSS;
    document.head.appendChild(style);
}

// Initialize theme manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.aanpThemeManager = new AANP_ThemeManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.aanpThemeManager) {
        window.aanpThemeManager.destroy();
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AANP_ThemeManager;
}