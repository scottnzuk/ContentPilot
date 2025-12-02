/**
 * Mobile Navigation and Touch Interactions for AI Auto News Poster Dashboard
 * 
 * Handles mobile navigation, touch gestures, sidebar management,
 * and responsive interactions for mobile devices.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

class AANP_MobileNavigation {
    
    /**
     * Mobile navigation state
     */
    private isMobile = false;
    private isSidebarOpen = false;
    private isLandscape = false;
    private touchStartX = 0;
    private touchStartY = 0;
    private touchEndX = 0;
    private touchEndY = 0;
    
    /**
     * Mobile breakpoints
     */
    private mobileBreakpoint = 768;
    private smallMobileBreakpoint = 480;
    private tinyMobileBreakpoint = 360;
    
    /**
     * Touch configuration
     */
    private swipeThreshold = 50;
    private maxTapDuration = 200;
    private minSwipeDistance = 30;
    
    /**
     * DOM elements
     */
    private sidebar = null;
    private contentArea = null;
    private menuToggle = null;
    private backdrop = null;
    
    constructor() {
        this.init();
    }
    
    /**
     * Initialize mobile navigation
     */
    init() {
        this.detectMobile();
        this.setupElements();
        this.bindEvents();
        this.setupTouchGestures();
        this.handleOrientationChange();
        
        if (this.isMobile) {
            this.setupMobileNavigation();
        }
        
        console.log('Mobile Navigation initialized');
    }
    
    /**
     * Detect if device is mobile
     */
    detectMobile() {
        this.isMobile = window.innerWidth <= this.mobileBreakpoint;
        this.isLandscape = window.innerWidth > window.innerHeight;
    }
    
    /**
     * Setup DOM elements
     */
    setupElements() {
        this.sidebar = document.querySelector('.dashboard-sidebar');
        this.contentArea = document.querySelector('.dashboard-content');
        this.menuToggle = document.querySelector('.menu-toggle');
        this.backdrop = this.createBackdrop();
    }
    
    /**
     * Create backdrop element
     */
    createBackdrop() {
        const backdrop = document.createElement('div');
        backdrop.className = 'mobile-backdrop';
        backdrop.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        `;
        
        document.body.appendChild(backdrop);
        return backdrop;
    }
    
    /**
     * Bind event listeners
     */
    bindEvents() {
        // Window resize
        window.addEventListener('resize', this.handleResize.bind(this));
        
        // Menu toggle
        if (this.menuToggle) {
            this.menuToggle.addEventListener('click', this.toggleSidebar.bind(this));
        }
        
        // Backdrop click
        this.backdrop.addEventListener('click', this.closeSidebar.bind(this));
        
        // Keyboard navigation
        document.addEventListener('keydown', this.handleKeydown.bind(this));
        
        // Focus management
        document.addEventListener('focusin', this.handleFocusIn.bind(this));
        
        // Prevent default touch behaviors
        document.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
        document.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        document.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: false });
    }
    
    /**
     * Setup touch gestures
     */
    setupTouchGestures() {
        if (!this.isMobile) return;
        
        // Swipe gestures for sidebar
        if (this.sidebar) {
            this.sidebar.addEventListener('touchstart', this.handleSidebarTouchStart.bind(this), { passive: true });
            this.sidebar.addEventListener('touchmove', this.handleSidebarTouchMove.bind(this), { passive: false });
            this.sidebar.addEventListener('touchend', this.handleSidebarTouchEnd.bind(this), { passive: true });
        }
        
        // Swipe gestures for content area
        if (this.contentArea) {
            this.contentArea.addEventListener('touchstart', this.handleContentTouchStart.bind(this), { passive: true });
            this.contentArea.addEventListener('touchend', this.handleContentTouchEnd.bind(this), { passive: true });
        }
        
        // Pull to refresh
        this.setupPullToRefresh();
        
        // Scroll direction detection
        this.setupScrollDirectionDetection();
    }
    
    /**
     * Setup pull to refresh
     */
    setupPullToRefresh() {
        let startY = 0;
        let pullDistance = 0;
        let isPulling = false;
        const pullThreshold = 80;
        
        this.contentArea?.addEventListener('touchstart', (e) => {
            if (this.contentArea.scrollTop === 0) {
                startY = e.touches[0].clientY;
                isPulling = true;
            }
        }, { passive: true });
        
        this.contentArea?.addEventListener('touchmove', (e) => {
            if (isPulling) {
                pullDistance = e.touches[0].clientY - startY;
                
                if (pullDistance > 0 && this.contentArea.scrollTop === 0) {
                    e.preventDefault();
                    this.showPullToRefreshIndicator(pullDistance);
                }
            }
        }, { passive: false });
        
        this.contentArea?.addEventListener('touchend', () => {
            if (isPulling && pullDistance > pullThreshold) {
                this.triggerRefresh();
            }
            this.hidePullToRefreshIndicator();
            isPulling = false;
            pullDistance = 0;
        }, { passive: true });
    }
    
    /**
     * Setup scroll direction detection
     */
    setupScrollDirectionDetection() {
        let lastScrollTop = 0;
        let scrollTimeout;
        
        this.contentArea?.addEventListener('scroll', () => {
            const scrollTop = this.contentArea.scrollTop;
            const scrollDirection = scrollTop > lastScrollTop ? 'down' : 'up';
            
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.handleScrollDirectionChange(scrollDirection);
            }, 150);
            
            lastScrollTop = scrollTop;
        }, { passive: true });
    }
    
    /**
     * Handle window resize
     */
    handleResize() {
        const wasMobile = this.isMobile;
        this.detectMobile();
        
        if (wasMobile !== this.isMobile) {
            if (this.isMobile) {
                this.setupMobileNavigation();
            } else {
                this.teardownMobileNavigation();
            }
        }
        
        // Handle orientation change
        this.handleOrientationChange();
    }
    
    /**
     * Handle orientation change
     */
    handleOrientationChange() {
        const wasLandscape = this.isLandscape;
        this.isLandscape = window.innerWidth > window.innerHeight;
        
        if (wasLandscape !== this.isLandscape) {
            this.onOrientationChange(this.isLandscape);
        }
    }
    
    /**
     * Toggle sidebar
     */
    toggleSidebar() {
        if (this.isSidebarOpen) {
            this.closeSidebar();
        } else {
            this.openSidebar();
        }
    }
    
    /**
     * Open sidebar
     */
    openSidebar() {
        if (!this.sidebar || !this.isMobile) return;
        
        this.isSidebarOpen = true;
        this.sidebar.classList.add('open');
        this.backdrop.style.opacity = '1';
        this.backdrop.style.visibility = 'visible';
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Focus management
        this.trapFocus(this.sidebar);
        
        // Announce to screen readers
        this.announceToScreenReader('Navigation menu opened');
    }
    
    /**
     * Close sidebar
     */
    closeSidebar() {
        if (!this.sidebar || !this.isMobile) return;
        
        this.isSidebarOpen = false;
        this.sidebar.classList.remove('open');
        this.backdrop.style.opacity = '0';
        this.backdrop.style.visibility = 'hidden';
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Restore focus
        if (this.menuToggle) {
            this.menuToggle.focus();
        }
        
        // Announce to screen readers
        this.announceToScreenReader('Navigation menu closed');
    }
    
    /**
     * Handle keydown events
     */
    handleKeydown(e) {
        switch (e.key) {
            case 'Escape':
                if (this.isSidebarOpen) {
                    e.preventDefault();
                    this.closeSidebar();
                }
                break;
            case 'Tab':
                if (this.isSidebarOpen) {
                    this.trapFocus(e);
                }
                break;
        }
    }
    
    /**
     * Handle focus events
     */
    handleFocusIn(e) {
        if (this.isSidebarOpen && !this.sidebar.contains(e.target)) {
            // Redirect focus back to sidebar
            this.sidebar.focus();
        }
    }
    
    /**
     * Handle touch start
     */
    handleTouchStart(e) {
        this.touchStartX = e.touches[0].clientX;
        this.touchStartY = e.touches[0].clientY;
    }
    
    /**
     * Handle touch move
     */
    handleTouchMove(e) {
        if (!this.isMobile) return;
        
        // Prevent default for sidebar swipes
        if (this.isSwipingSidebar(e)) {
            e.preventDefault();
        }
    }
    
    /**
     * Handle touch end
     */
    handleTouchEnd(e) {
        if (!this.isMobile) return;
        
        this.touchEndX = e.changedTouches[0].clientX;
        this.touchEndY = e.changedTouches[0].clientY;
        
        const deltaX = this.touchEndX - this.touchStartX;
        const deltaY = this.touchEndY - this.touchStartY;
        const duration = Date.now() - this.touchStartTime;
        
        // Swipe gestures
        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > this.minSwipeDistance) {
            if (deltaX > 0) {
                this.handleSwipeRight();
            } else {
                this.handleSwipeLeft();
            }
        }
        
        // Tap gesture
        if (duration < this.maxTapDuration && Math.abs(deltaX) < 10 && Math.abs(deltaY) < 10) {
            this.handleTap(e.target);
        }
    }
    
    /**
     * Handle sidebar touch start
     */
    handleSidebarTouchStart(e) {
        this.touchStartTime = Date.now();
    }
    
    /**
     * Handle sidebar touch move
     */
    handleSidebarTouchMove(e) {
        if (!this.isSidebarOpen) return;
        
        const touch = e.touches[0];
        const progress = (touch.clientX / window.innerWidth) * 100;
        
        if (progress > 10) {
            this.sidebar.style.transform = `translateX(${(progress - 10) * -1}%)`;
        }
    }
    
    /**
     * Handle sidebar touch end
     */
    handleSidebarTouchEnd(e) {
        if (!this.isSidebarOpen) return;
        
        this.sidebar.style.transform = '';
        
        const deltaX = this.touchEndX - this.touchStartX;
        if (deltaX > this.swipeThreshold) {
            this.closeSidebar();
        }
    }
    
    /**
     * Handle content touch start
     */
    handleContentTouchStart(e) {
        this.touchStartTime = Date.now();
    }
    
    /**
     * Handle content touch end
     */
    handleContentTouchEnd(e) {
        const deltaX = this.touchEndX - this.touchStartX;
        const deltaY = this.touchEndY - this.touchStartY;
        
        // Swipe right to open sidebar (from content area)
        if (deltaX > this.swipeThreshold && Math.abs(deltaX) > Math.abs(deltaY)) {
            if (!this.isSidebarOpen && e.target.closest('.dashboard-content')) {
                this.openSidebar();
            }
        }
    }
    
    /**
     * Setup mobile navigation
     */
    setupMobileNavigation() {
        // Add mobile classes
        document.body.classList.add('mobile-device');
        
        // Create menu toggle if not exists
        this.createMenuToggle();
        
        // Setup mobile styles
        this.applyMobileStyles();
        
        // Setup accessibility
        this.setupAccessibility();
    }
    
    /**
     * Teardown mobile navigation
     */
    teardownMobileNavigation() {
        document.body.classList.remove('mobile-device');
        this.closeSidebar();
        this.removeMenuToggle();
    }
    
    /**
     * Create menu toggle button
     */
    createMenuToggle() {
        if (this.menuToggle) return;
        
        this.menuToggle = document.createElement('button');
        this.menuToggle.className = 'menu-toggle btn btn-outline';
        this.menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        this.menuToggle.setAttribute('aria-label', 'Toggle navigation menu');
        this.menuToggle.style.cssText = `
            display: block;
            padding: 0.75rem;
            font-size: 1.25rem;
            border-radius: var(--border-radius);
            background: var(--glass-white);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
        `;
        
        // Insert after header title
        const header = document.querySelector('.header-content');
        const logoSection = document.querySelector('.logo-section');
        if (header && logoSection) {
            header.insertBefore(this.menuToggle, logoSection.nextSibling);
        }
        
        this.menuToggle.addEventListener('click', this.toggleSidebar.bind(this));
    }
    
    /**
     * Remove menu toggle button
     */
    removeMenuToggle() {
        if (this.menuToggle && this.menuToggle.parentNode) {
            this.menuToggle.parentNode.removeChild(this.menuToggle);
            this.menuToggle = null;
        }
    }
    
    /**
     * Apply mobile styles
     */
    applyMobileStyles() {
        // Add mobile-specific CSS classes
        if (window.innerWidth <= this.tinyMobileBreakpoint) {
            document.body.classList.add('tiny-mobile');
        } else if (window.innerWidth <= this.smallMobileBreakpoint) {
            document.body.classList.add('small-mobile');
        } else {
            document.body.classList.add('large-mobile');
        }
    }
    
    /**
     * Setup accessibility features
     */
    setupAccessibility() {
        // Add ARIA labels
        if (this.sidebar) {
            this.sidebar.setAttribute('aria-label', 'Main navigation');
            this.sidebar.setAttribute('role', 'navigation');
        }
        
        // Add skip link
        this.createSkipLink();
        
        // Setup screen reader announcements
        this.setupScreenReaderAnnouncements();
    }
    
    /**
     * Create skip link
     */
    createSkipLink() {
        if (document.querySelector('.skip-link')) return;
        
        const skipLink = document.createElement('a');
        skipLink.className = 'skip-link';
        skipLink.href = '#main-content';
        skipLink.textContent = 'Skip to main content';
        skipLink.style.cssText = `
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--primary-color);
            color: white;
            padding: 8px;
            border-radius: var(--border-radius);
            text-decoration: none;
            z-index: 10000;
            transition: top 0.3s;
        `;
        
        skipLink.addEventListener('focus', () => {
            skipLink.style.top = '6px';
        });
        
        skipLink.addEventListener('blur', () => {
            skipLink.style.top = '-40px';
        });
        
        document.body.insertBefore(skipLink, document.body.firstChild);
    }
    
    /**
     * Setup screen reader announcements
     */
    setupScreenReaderAnnouncements() {
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        announcer.className = 'sr-only';
        announcer.style.cssText = `
            position: absolute;
            left: -10000px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        `;
        
        document.body.appendChild(announcer);
    }
    
    /**
     * Trap focus within element
     */
    trapFocus(e) {
        const focusableElements = e.target.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        e.target.addEventListener('keydown', (event) => {
            if (event.key === 'Tab') {
                if (event.shiftKey) {
                    if (document.activeElement === firstElement) {
                        lastElement.focus();
                        event.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        firstElement.focus();
                        event.preventDefault();
                    }
                }
            }
        });
    }
    
    /**
     * Announce to screen reader
     */
    announceToScreenReader(message) {
        const announcer = document.querySelector('[aria-live="polite"]');
        if (announcer) {
            announcer.textContent = message;
            setTimeout(() => {
                announcer.textContent = '';
            }, 1000);
        }
    }
    
    /**
     * Handle swipe left
     */
    handleSwipeLeft() {
        if (this.isSidebarOpen) {
            this.closeSidebar();
        }
    }
    
    /**
     * Handle swipe right
     */
    handleSwipeRight() {
        if (!this.isSidebarOpen) {
            this.openSidebar();
        }
    }
    
    /**
     * Handle tap gesture
     */
    handleTap(target) {
        // Handle tap on menu items
        if (target.closest('.menu-item a')) {
            this.handleMenuItemTap(target);
        }
        
        // Handle tap on buttons
        if (target.closest('.btn')) {
            this.handleButtonTap(target);
        }
    }
    
    /**
     * Handle menu item tap
     */
    handleMenuItemTap(target) {
        const menuItem = target.closest('.menu-item');
        if (menuItem) {
            // Close sidebar on mobile after menu selection
            setTimeout(() => {
                this.closeSidebar();
            }, 300);
            
            // Update active state
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            menuItem.classList.add('active');
        }
    }
    
    /**
     * Handle button tap
     */
    handleButtonTap(target) {
        // Add visual feedback for touch
        target.style.transform = 'scale(0.95)';
        setTimeout(() => {
            target.style.transform = '';
        }, 150);
    }
    
    /**
     * Check if swiping sidebar
     */
    isSwipingSidebar(e) {
        if (!this.isSidebarOpen) return false;
        
        const target = e.target;
        return target.closest('.dashboard-sidebar') || 
               (this.touchStartX < window.innerWidth * 0.2 && e.type === 'touchmove');
    }
    
    /**
     * Show pull to refresh indicator
     */
    showPullToRefreshIndicator(distance) {
        // Implementation for pull to refresh indicator
        console.log('Pull to refresh:', distance);
    }
    
    /**
     * Hide pull to refresh indicator
     */
    hidePullToRefreshIndicator() {
        // Hide pull to refresh indicator
    }
    
    /**
     * Trigger refresh
     */
    triggerRefresh() {
        // Trigger dashboard refresh
        console.log('Pull to refresh triggered');
    }
    
    /**
     * Handle scroll direction change
     */
    handleScrollDirectionChange(direction) {
        // Hide/show navigation based on scroll direction
        if (direction === 'down') {
            document.body.classList.add('hide-navigation');
        } else {
            document.body.classList.remove('hide-navigation');
        }
    }
    
    /**
     * Handle orientation change
     */
    onOrientationChange(isLandscape) {
        if (isLandscape) {
            document.body.classList.add('landscape');
            document.body.classList.remove('portrait');
        } else {
            document.body.classList.add('portrait');
            document.body.classList.remove('landscape');
        }
        
        // Adjust layout for orientation
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);
    }
    
    /**
     * Get mobile navigation state
     */
    getState() {
        return {
            isMobile: this.isMobile,
            isSidebarOpen: this.isSidebarOpen,
            isLandscape: this.isLandscape,
            touchStartX: this.touchStartX,
            touchStartY: this.touchStartY,
            touchEndX: this.touchEndX,
            touchEndY: this.touchEndY
        };
    }
}

// Initialize mobile navigation when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.aanpMobileNav = new AANP_MobileNavigation();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AANP_MobileNavigation;
}