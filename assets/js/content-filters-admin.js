/**
 * Content Filters Admin JavaScript
 *
 * Handles live preview, bundle selection, keyword management,
 * and AJAX interactions for the content filtering system.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // Global variables
    let currentFilters = {};
    let previewTimeout;
    let selectedBundle = null;

    $(document).ready(function() {
        initializeContentFilters();
    });

    /**
     * Initialize content filters functionality
     */
    function initializeContentFilters() {
        setupEventHandlers();
        loadCurrentFilters();
        loadUserPresets();
        setupLivePreview();
        setupBundleSelector();
        updatePreviewStats();
    }

    /**
     * Setup event handlers
     */
    function setupEventHandlers() {
        // Bundle selector
        $('#bundle-selector').on('change', handleBundleSelection);
        $('#apply-bundle').on('click', applySelectedBundle);
        $('#preview-bundle').on('click', previewSelectedBundle);

        // Keyword inputs
        $('#positive-keywords, #negative-keywords').on('input', debounce(updatePreview, 1000));
        $('#content-age-limit, #priority-regions, #language-priority').on('change', updatePreview);

        // Quality threshold
        $('#quality-threshold').on('input', function() {
            $('#quality-value').text($(this).val());
            updatePreview();
        });

        // Checkboxes
        $('#duplicate-detection, #region-bias, #auto-categorization').on('change', updatePreview);

        // Preset management
        $('#save-preset').on('click', saveFilterPreset);
        $('#preset-list').on('click', '.load-preset', loadSelectedPreset);

        // Preview controls
        $('#refresh-preview').on('click', refreshPreview);
        $('#reset-filters').on('click', resetToDefault);
        $('#export-filters').on('click', showExportModal);
        $('#import-filters').on('click', showImportModal);

        // Modal controls
        $('.aanp-modal-close, .aanp-modal').on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        $('#copy-export').on('click', copyExportData);
        $('#confirm-import').on('click', importFilterSettings);

        // Multi-select for regions
        setupMultiSelect();
    }

    /**
     * Setup multi-select functionality
     */
    function setupMultiSelect() {
        const $select = $('#priority-regions');
        const $options = $select.find('option');
        
        // Style as multi-select
        $select.addClass('aanp-multi-select');
        
        // Handle selection changes
        $select.on('change', function() {
            updatePreview();
        });
    }

    /**
     * Handle bundle selection
     */
    function handleBundleSelection() {
        const selectedValue = $(this).val();
        const $selectedOption = $(this).find('option:selected');
        
        if (selectedValue) {
            selectedBundle = {
                slug: selectedValue,
                name: $selectedOption.text().replace('Default', '').trim(),
                feeds: parseInt($selectedOption.data('feeds')),
                isDefault: $selectedOption.data('default') === 'true'
            };
            
            // Enable action buttons
            $('#apply-bundle, #preview-bundle').prop('disabled', false);
            
            // Show bundle info
            showBundleInfo();
            
            // Load bundle data
            loadBundleData(selectedValue);
        } else {
            selectedBundle = null;
            $('#apply-bundle, #preview-bundle').prop('disabled', true);
            hideBundleInfo();
        }
    }

    /**
     * Show bundle information
     */
    function showBundleInfo() {
        if (!selectedBundle) return;
        
        $('#bundle-name').text(selectedBundle.name);
        $('#bundle-info').slideDown();
        
        // Get bundle details via AJAX
        $.ajax({
            url: aanpFilterAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aanp_get_bundle_feeds',
                bundle_slug: selectedBundle.slug,
                nonce: aanpFilterAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const bundle = response.data.bundle;
                    $('#bundle-description').text(bundle.description);
                    $('#bundle-feeds-count').html(`<strong>${response.data.enabled_feeds}</strong> feeds`);
                    $('#bundle-keywords').html(`<strong>${(bundle.positive_keywords || '').split(',').length}</strong> keywords`);
                    $('#bundle-categories').html(`<strong>${(bundle.categories || []).length}</strong> categories`);
                }
            }
        });
    }

    /**
     * Hide bundle information
     */
    function hideBundleInfo() {
        $('#bundle-info').slideUp();
    }

    /**
     * Apply selected bundle
     */
    function applySelectedBundle() {
        if (!selectedBundle) return;
        
        const $button = $('#apply-bundle');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text(aanpFilterAjax.strings.loading);
        
        // Redirect to apply bundle
        window.location.href = `admin.php?page=aanp-content-filters&aanp_action=apply_content_bundle&bundle=${selectedBundle.slug}`;
    }

    /**
     * Preview selected bundle
     */
    function previewSelectedBundle() {
        if (!selectedBundle) return;
        
        // Load bundle keywords into the form and refresh preview
        $.ajax({
            url: aanpFilterAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aanp_get_bundle_feeds',
                bundle_slug: selectedBundle.slug,
                nonce: aanpFilterAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const bundle = response.data.bundle;
                    $('#positive-keywords').val(bundle.positive_keywords || '');
                    $('#negative-keywords').val(bundle.negative_keywords || '');
                    $('#content-age-limit').val(bundle.content_age_limit || 7);
                    
                    // Set priority regions
                    const regions = (bundle.priority_regions || '').split(',');
                    $('#priority-regions option').each(function() {
                        $(this).prop('selected', regions.includes($(this).val()));
                    });
                    
                    refreshPreview();
                }
            }
        });
    }

    /**
     * Load current user filters
     */
    function loadCurrentFilters() {
        // This would be populated server-side in the PHP template
        // For now, we'll initialize with defaults
        currentFilters = {
            positive_keywords: $('#positive-keywords').val(),
            negative_keywords: $('#negative-keywords').val(),
            content_age_limit: $('#content-age-limit').val(),
            priority_regions: $('#priority-regions').val()
        };
    }

    /**
     * Load user presets
     */
    function loadUserPresets() {
        // Load presets via AJAX
        loadPresetsList();
    }

    /**
     * Load presets list
     */
    function loadPresetsList() {
        const $container = $('#preset-list');
        
        // Show loading state
        $container.html('<div class="aanp-loading">Loading presets...</div>');
        
        // In a real implementation, this would load from the server
        // For demo purposes, we'll show some example presets
        const examplePresets = [
            { name: 'Tech News', positive: 'technology, AI, software, innovation' },
            { name: 'Health Focus', positive: 'health, medical, wellness, fitness' },
            { name: 'Business Only', positive: 'business, economy, finance, market', negative: '-politics, -sports' }
        ];
        
        let html = '';
        examplePresets.forEach(function(preset) {
            html += `
                <div class="aanp-preset-item">
                    <span class="aanp-preset-name">${preset.name}</span>
                    <button type="button" class="button button-small load-preset" data-name="${preset.name}">
                        Load
                    </button>
                </div>
            `;
        });
        
        if (html) {
            $container.html(html);
        } else {
            $container.html('<div class="aanp-no-presets">No saved presets found</div>');
        }
    }

    /**
     * Save filter preset
     */
    function saveFilterPreset() {
        const presetName = $('#preset-name').val().trim();
        
        if (!presetName) {
            alert('Please enter a preset name');
            return;
        }
        
        const filters = getCurrentFilterSettings();
        
        $.ajax({
            url: aanpFilterAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aanp_save_filter_preset',
                preset_name: presetName,
                positive_keywords: filters.positive_keywords,
                negative_keywords: filters.negative_keywords,
                content_age_limit: filters.content_age_limit,
                nonce: aanpFilterAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#preset-name').val('');
                    showNotification('Preset saved successfully!', 'success');
                    loadPresetsList();
                } else {
                    showNotification('Failed to save preset: ' + (response.data.error || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showNotification('Failed to save preset', 'error');
            }
        });
    }

    /**
     * Load selected preset
     */
    function loadSelectedPreset() {
        const presetName = $(this).data('name');
        
        $.ajax({
            url: aanpFilterAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aanp_load_filter_preset',
                preset_name: presetName,
                nonce: aanpFilterAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data.preset) {
                    const preset = response.data.preset;
                    $('#positive-keywords').val(preset.positive_keywords || '');
                    $('#negative-keywords').val(preset.negative_keywords || '');
                    
                    // Load advanced settings if available
                    if (preset.advanced_settings) {
                        try {
                            const settings = JSON.parse(preset.advanced_settings);
                            if (settings.content_age_limit) {
                                $('#content-age-limit').val(settings.content_age_limit);
                            }
                        } catch (e) {
                            console.log('Failed to parse advanced settings');
                        }
                    }
                    
                    refreshPreview();
                    showNotification(`Preset "${presetName}" loaded`, 'success');
                } else {
                    showNotification('Failed to load preset: ' + (response.data.error || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showNotification('Failed to load preset', 'error');
            }
        });
    }

    /**
     * Get current filter settings
     */
    function getCurrentFilterSettings() {
        return {
            positive_keywords: $('#positive-keywords').val().trim(),
            negative_keywords: $('#negative-keywords').val().trim(),
            content_age_limit: parseInt($('#content-age-limit').val()),
            priority_regions: $('#priority-regions').val().join(','),
            language_priority: $('#language-priority').val(),
            quality_threshold: parseInt($('#quality-threshold').val()),
            duplicate_detection: $('#duplicate-detection').is(':checked'),
            region_bias: $('#region-bias').is(':checked'),
            auto_categorization: $('#auto-categorization').is(':checked')
        };
    }

    /**
     * Setup live preview functionality
     */
    function setupLivePreview() {
        // Auto-refresh preview every 30 seconds
        setInterval(refreshPreview, 30000);
        
        // Initial preview
        refreshPreview();
    }

    /**
     * Refresh preview
     */
    function refreshPreview() {
        const filters = getCurrentFilterSettings();
        const $button = $('#refresh-preview');
        const originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Refreshing...');
        
        $.ajax({
            url: aanpFilterAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'aanp_get_live_preview',
                ...filters,
                nonce: aanpFilterAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayPreviewResults(response.data);
                } else {
                    showPreviewError(response.data.message || 'Failed to generate preview');
                }
            },
            error: function() {
                showPreviewError('Network error occurred');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }

    /**
     * Update preview (debounced)
     */
    function updatePreview() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(refreshPreview, 1000);
    }

    /**
     * Display preview results
     */
    function displayPreviewResults(data) {
        const $container = $('#preview-results');
        
        if (!data.preview_items || data.preview_items.length === 0) {
            $container.html(`
                <div class="aanp-preview-empty">
                    <span class="dashicons dashicons-info"></span>
                    <p>${aanpFilterAjax.strings.no_results}</p>
                </div>
            `);
            return;
        }
        
        let html = `
            <div class="aanp-preview-stats">
                <div class="aanp-stat accepted">
                    <span class="count">${data.statistics.accepted_items}</span>
                    <span class="label">Accepted</span>
                </div>
                <div class="aanp-stat rejected">
                    <span class="count">${data.statistics.rejected_items}</span>
                    <span class="label">Rejected</span>
                </div>
                <div class="aanp-stat rate">
                    <span class="count">${data.statistics.acceptance_rate}%</span>
                    <span class="label">Acceptance Rate</span>
                </div>
            </div>
            
            <div class="aanp-preview-items">
        `;
        
        data.preview_items.forEach(function(item, index) {
            const statusClass = item.accepted ? 'accepted' : 'rejected';
            const statusIcon = item.accepted ? 'yes-alt' : 'dismiss';
            const statusText = item.accepted ? 'ACCEPTED' : 'REJECTED';
            
            html += `
                <div class="aanp-preview-item ${statusClass}">
                    <div class="aanp-item-header">
                        <span class="aanp-item-status">
                            <span class="dashicons dashicons-${statusIcon}"></span>
                            ${statusText}
                        </span>
                        <span class="aanp-item-date">${formatDate(item.pub_date)}</span>
                    </div>
                    <div class="aanp-item-content">
                        <h4 class="aanp-item-title">${escapeHtml(item.title)}</h4>
                        <p class="aanp-item-description">${escapeHtml(item.description)}</p>
                        <div class="aanp-item-meta">
                            <span class="aanp-item-source">${escapeHtml(item.source)}</span>
                            ${item.filter_result.matched_keywords.length > 0 ? 
                                `<span class="aanp-matched-keywords">Matches: ${item.filter_result.matched_keywords.join(', ')}</span>` : ''
                            }
                        </div>
                    </div>
                    ${item.filter_result.rejection_reasons.length > 0 ?
                        `<div class="aanp-rejection-reasons">
                            <strong>Reasons:</strong>
                            <ul>
                                ${item.filter_result.rejection_reasons.map(reason => `<li>${escapeHtml(reason)}</li>`).join('')}
                            </ul>
                        </div>` : ''
                    }
                </div>
            `;
        });
        
        html += '</div>';
        $container.html(html);
        
        updatePreviewStats(data.statistics);
    }

    /**
     * Show preview error
     */
    function showPreviewError(message) {
        $('#preview-results').html(`
            <div class="aanp-preview-error">
                <span class="dashicons dashicons-warning"></span>
                <p>${escapeHtml(message)}</p>
            </div>
        `);
    }

    /**
     * Update preview statistics
     */
    function updatePreviewStats(stats) {
        if (stats) {
            $('#preview-stats').html(` 
                Showing ${stats.accepted_items} accepted / ${stats.total_items} total items 
                (${stats.acceptance_rate}% acceptance rate)
            `);
        } else {
            $('#preview-stats').text('Ready to preview filtering results');
        }
    }

    /**
     * Reset filters to default
     */
    function resetToDefault() {
        if (!confirm(aanpFilterAjax.strings.confirm_reset)) {
            return;
        }
        
        $('#positive-keywords').val('news, breaking, latest, report, update, announcement');
        $('#negative-keywords').val('-opinion, -editorial, -satire, -entertainment, -celebrity, -gossip');
        $('#content-age-limit').val('7');
        $('#language-priority').val('en');
        $('#quality-threshold').val('70').trigger('input');
        $('#duplicate-detection, #region-bias, #auto-categorization').prop('checked', true);
        $('#priority-regions option').prop('selected', false).filter('[value="UK"], [value="USA"], [value="EU"]').prop('selected', true);
        
        refreshPreview();
        showNotification('Filters reset to default', 'success');
    }

    /**
     * Show export modal
     */
    function showExportModal() {
        const filters = getCurrentFilterSettings();
        const exportData = JSON.stringify({
            filters: filters,
            timestamp: new Date().toISOString(),
            version: '2.0.0'
        }, null, 2);
        
        $('#export-data').val(exportData);
        $('#modal-title').text('Export Filter Settings');
        $('#export-section').show();
        $('#import-section').hide();
        $('#aanp-import-export-modal').show();
    }

    /**
     * Show import modal
     */
    function showImportModal() {
        $('#modal-title').text('Import Filter Settings');
        $('#export-section').hide();
        $('#import-section').show();
        $('#import-data').val('');
        $('#aanp-import-export-modal').show();
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('#aanp-import-export-modal').hide();
    }

    /**
     * Copy export data to clipboard
     */
    function copyExportData() {
        const $textarea = $('#export-data');
        $textarea.select();
        document.execCommand('copy');
        showNotification('Settings copied to clipboard!', 'success');
    }

    /**
     * Import filter settings
     */
    function importFilterSettings() {
        const importData = $('#import-data').val().trim();
        
        if (!importData) {
            showNotification('Please paste exported settings', 'error');
            return;
        }
        
        try {
            const data = JSON.parse(importData);
            
            if (data.filters) {
                const filters = data.filters;
                $('#positive-keywords').val(filters.positive_keywords || '');
                $('#negative-keywords').val(filters.negative_keywords || '');
                $('#content-age-limit').val(filters.content_age_limit || 7);
                $('#language-priority').val(filters.language_priority || 'en');
                $('#quality-threshold').val(filters.quality_threshold || 70).trigger('input');
                $('#duplicate-detection').prop('checked', filters.duplicate_detection !== false);
                $('#region-bias').prop('checked', filters.region_bias !== false);
                $('#auto-categorization').prop('checked', filters.auto_categorization !== false);
                
                // Set priority regions
                if (filters.priority_regions) {
                    const regions = filters.priority_regions.split(',');
                    $('#priority-regions option').each(function() {
                        $(this).prop('selected', regions.includes($(this).val()));
                    });
                }
                
                closeModal();
                refreshPreview();
                showNotification('Settings imported successfully!', 'success');
            } else {
                throw new Error('Invalid settings format');
            }
        } catch (e) {
            showNotification('Failed to parse settings: ' + e.message, 'error');
        }
    }

    /**
     * Show notification
     */
    function showNotification(message, type) {
        const $notification = $(`
            <div class="aanp-notification aanp-notification-${type}">
                <span class="dashicons dashicons-${type === 'success' ? 'yes-alt' : 'warning'}"></span>
                <span>${escapeHtml(message)}</span>
            </div>
        `);
        
        $('body').append($notification);
        
        setTimeout(() => {
            $notification.addClass('show');
        }, 100);
        
        setTimeout(() => {
            $notification.removeClass('show');
            setTimeout(() => $notification.remove(), 300);
        }, 3000);
    }

    /**
     * Utility functions
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function escapeHtml(text) {
        const map = {
            '&': '&',
            '<': '<',
            '>': '>',
            '"': '"',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 0) {
            return 'Today';
        } else if (diffDays === 1) {
            return 'Yesterday';
        } else if (diffDays < 7) {
            return `${diffDays} days ago`;
        } else {
            return date.toLocaleDateString();
        }
    }

    // Add CSS for spinning icon
    const style = document.createElement('style');
    style.textContent = `
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

})(jQuery);