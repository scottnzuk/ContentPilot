"use strict";

function _typeof(obj) { if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") { _typeof = function _typeof(obj) { return typeof obj; }; } else { _typeof = function _typeof(obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }; } return _typeof(obj); }

jQuery(document).ready(function ($) {
  // Add RSS Feed functionality
  $('#add-feed').on('click', function () {
    var container = $('#rss-feeds-container');
    var newRow = $('<div class="rss-feed-row">');
    newRow.html('<input type="url" name="aanp_settings[rss_feeds][]" value="" class="regular-text" placeholder="https://example.com/feed.xml" /> <button type="button" class="button remove-feed">Remove</button>');
    container.append(newRow);
  }); // Remove RSS Feed functionality

  $(document).on('click', '.remove-feed', function () {
    $(this).closest('.rss-feed-row').remove();
  }); // Generate Posts functionality

  $('#aanp-generate-posts').on('click', function () {
    var button = $(this);
    var statusDiv = $('#aanp-generation-status');
    var statusText = $('#aanp-status-text');
    var progressBar = $('.aanp-progress-bar');
    var resultsDiv = $('#aanp-generation-results');
    var resultsList = $('#aanp-results-list'); // Disable button and show progress

    button.prop('disabled', true);
    button.find('.dashicons').addClass('spin');
    statusDiv.show();
    resultsDiv.hide();
    resultsList.empty(); // Reset progress

    progressBar.css('width', '0%');
    statusText.text(aanp_ajax.generating_text); // Simulate progress

    var progress = 0;
    var progressInterval = setInterval(function () {
      progress += Math.random() * 15;
      if (progress > 90) progress = 90;
      progressBar.css('width', progress + '%');
    }, 500); // Make AJAX request

    $.ajax({
      url: aanp_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'aanp_generate_posts',
        nonce: aanp_ajax.nonce
      },
      success: function success(response) {
        clearInterval(progressInterval);
        progressBar.css('width', '100%'); // Validate response structure

        if (response && _typeof(response) === 'object' && response.success === true) {
          // Safely extract and validate message
          var message = response.data && typeof response.data.message === 'string' ? escapeHtml(response.data.message) : 'Posts generated successfully';
          statusText.html('<span style="color: #00a32a;">✓ ' + message + '</span>'); // Display generated posts with security validation

          if (response.data && response.data.posts && Array.isArray(response.data.posts) && response.data.posts.length > 0) {
            var postsHtml = '<ul>';
            $.each(response.data.posts, function (index, post) {
              // Validate post object structure
              if (post && _typeof(post) === 'object' && typeof post.title === 'string' && typeof post.edit_link === 'string') {
                postsHtml += '<li>'; // Escape title for HTML context

                postsHtml += '<strong>' + escapeHtml(post.title) + '</strong> '; // Validate and safely escape URL for href context

                if (isValidUrl(post.edit_link) && isSafeUrl(post.edit_link)) {
                  var safeUrl = escapeUrl(post.edit_link);
                  postsHtml += '<a href="' + safeUrl + '" class="button button-small" target="_blank" rel="noopener noreferrer">Edit Post</a>';
                } else {
                  postsHtml += '<span class="button button-small disabled" style="opacity: 0.6; cursor: not-allowed;">Invalid URL</span>';
                }

                postsHtml += '</li>';
              }
            });
            postsHtml += '</ul>';
            resultsList.html(postsHtml);
            resultsDiv.show();
          } // Show admin notice with sanitized message


          showAdminNotice(message, 'success');
        } else {
          // Handle error response with sanitized message
          var errorMsg = 'Operation failed';

          if (response && response.data && typeof response.data === 'string') {
            errorMsg = response.data;
          } else if (typeof response === 'string') {
            errorMsg = response;
          }

          statusText.html('<span style="color: #d63638;">✗ ' + escapeHtml(errorMsg) + '</span>');
          showAdminNotice(errorMsg, 'error');
        }
      },
      error: function error(xhr, status, _error) {
        clearInterval(progressInterval);
        progressBar.css('width', '100%'); // Secure error handling - don't expose sensitive information

        var errorMsg = 'Request failed. Please try again.';

        if (xhr.status === 403) {
          errorMsg = 'Access denied. Please refresh the page and try again.';
        } else if (xhr.status === 500) {
          errorMsg = 'Server error occurred. Please contact the administrator.';
        } else if (xhr.status === 0) {
          errorMsg = 'Network error. Please check your connection.';
        } // Don't log the actual error message to avoid information disclosure


        statusText.html('<span style="color: #d63638;">✗ ' + escapeHtml(errorMsg) + '</span>');
        showAdminNotice(errorMsg, 'error');
      },
      complete: function complete() {
        // Re-enable button
        button.prop('disabled', false);
        button.find('.dashicons').removeClass('spin'); // Hide progress after delay

        setTimeout(function () {
          statusDiv.fadeOut();
        }, 3000);
      }
    });
  }); // Helper function to escape HTML

  function escapeHtml(text) {
    var map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function (m) {
      return map[m];
    }); // URL escaping function for href attributes

    function escapeUrl(url) {
      if (typeof url !== 'string') {
        return '#';
      } // Encode special characters in URLs for safe href usage


      return encodeURIComponent(url);
    } // Additional security check for URLs


    function isSafeUrl(url) {
      if (typeof url !== 'string') {
        return false;
      } // Check for potential XSS patterns


      var dangerousPatterns = [/javascript:/i, /vbscript:/i, /onload=/i, /onerror=/i, /<script/i, /data:text\/html/i];
      return !dangerousPatterns.some(function (pattern) {
        return pattern.test(url);
      });
    }
  } // Helper function to show admin notices


  function showAdminNotice(message, type) {
    var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
    var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>'); // Insert after the page title

    $('.wrap h1').after(notice); // Make it dismissible

    notice.on('click', '.notice-dismiss', function () {
      notice.fadeOut();
    }); // Auto-hide success notices

    if (type === 'success') {
      setTimeout(function () {
        notice.fadeOut();
      }, 5000);
    }
  } // API Key visibility toggle


  $('#api_key').after('<button type="button" class="button" id="toggle-api-key" style="margin-left: 10px;">Show</button>');
  $('#toggle-api-key').on('click', function () {
    var apiKeyField = $('#api_key');
    var button = $(this);

    if (apiKeyField.attr('type') === 'password') {
      apiKeyField.attr('type', 'text');
      button.text('Hide');
    } else {
      apiKeyField.attr('type', 'password');
      button.text('Show');
    }
  }); // Form validation

  $('form').on('submit', function (e) {
    var apiKey = $('#api_key').val().trim();
    var provider = $('#llm_provider').val();

    if (!apiKey && provider !== 'custom') {
      e.preventDefault();
      alert('Please enter an API key for the selected LLM provider.');
      $('#api_key').focus();
      return false;
    } // Validate RSS feeds with enhanced security


    var hasValidFeed = false;
    $('input[name="aanp_settings[rss_feeds][]"]').each(function () {
      var feedUrl = $(this).val().trim();

      if (feedUrl && isValidUrl(feedUrl) && isSafeUrl(feedUrl)) {
        hasValidFeed = true;
        return false; // break loop
      }
    });

    if (!hasValidFeed) {
      e.preventDefault();
      alert('Please add at least one valid RSS feed URL.');
      return false;
    }
  }); // Enhanced URL validation helper with security checks

  function isValidUrl(string) {
    if (typeof string !== 'string' || string.length === 0) {
      return false;
    }

    try {
      var url = new URL(string); // Ensure protocol is http or https

      if (!['http:', 'https:'].includes(url.protocol)) {
        return false;
      }

      return true;
    } catch (_) {
      return false;
    }
  } // Cache purge functionality


  $('#aanp-purge-cache').on('click', function () {
    var button = $(this);

    if (!confirm('Are you sure you want to purge all cache? This action cannot be undone.')) {
      return;
    }

    button.prop('disabled', true).text('Purging...');
    $.ajax({
      url: aanp_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'aanp_purge_cache',
        nonce: aanp_ajax.nonce
      },
      success: function success(response) {
        if (response.success) {
          showAdminNotice('Cache purged successfully!', 'success');
        } else {
          showAdminNotice('Failed to purge cache.', 'error');
        }
      },
      error: function error() {
        showAdminNotice('Error purging cache. Please try again later.', 'error');
      },
      complete: function complete() {
        button.prop('disabled', false).text('Purge All Cache');
      }
    });
  }); // Humanizer test functionality

  $('#test-humanizer-btn').on('click', function () {
    var button = $(this);
    var inputText = $('#humanizer-test-input').val().trim();
    var resultsDiv = $('#humanizer-test-results');
    var originalContent = $('#original-content');
    var humanizedContent = $('#humanized-content');
    var statsContent = $('#humanization-stats');

    if (!inputText) {
      alert('Please enter some text to test the humanizer.');
      $('#humanizer-test-input').focus();
      return;
    } // Disable button and show loading


    button.prop('disabled', true).text('Processing...');
    resultsDiv.hide(); // Show original text

    originalContent.text(inputText); // Make AJAX request

    $.ajax({
      url: aanp_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'aanp_test_humanizer',
        text: inputText,
        strength: $('select[name="aanp_settings[humanizer_strength]"]').val() || 'medium',
        personality: $('input[name="aanp_settings[humanizer_personality]"]').val() || '',
        nonce: aanp_ajax.settings_nonce
      },
      success: function success(response) {
        if (response.success && response.data) {
          var data = response.data;

          if (data.success) {
            humanizedContent.text(data.humanized_content || data.content || 'No content received'); // Show metadata if available

            var stats = [];

            if (data.execution_time_ms) {
              stats.push('Processing time: ' + data.execution_time_ms + 'ms');
            }

            if (data.test_metadata) {
              var meta = data.test_metadata;

              if (meta.original_text_length) {
                stats.push('Original length: ' + meta.original_text_length + ' characters');
              }

              if (meta.humanized_text_length) {
                stats.push('Humanized length: ' + meta.humanized_text_length + ' characters');
              }

              if (meta.estimated_human_score) {
                stats.push('Estimated human score: ' + (meta.estimated_human_score * 100).toFixed(1) + '%');
              }
            }

            statsContent.html(stats.length > 0 ? stats.join(' | ') : 'Test completed'); // Add success styling

            humanizedContent.parent().css('border-color', '#00a32a');
            humanizedContent.parent().css('background-color', '#f0f8f0');
          } else {
            humanizedContent.text('Humanization failed: ' + (data.error || 'Unknown error'));
            humanizedContent.parent().css('border-color', '#d63638');
            humanizedContent.parent().css('background-color', '#fef2f2');
            statsContent.text('Error: ' + (data.error || 'Unknown error'));
          }
        } else {
          humanizedContent.text('Invalid response from server');
          humanizedContent.parent().css('border-color', '#d63638');
          statsContent.text('Server error or invalid response');
        }

        resultsDiv.show();
      },
      error: function error(xhr, status, _error2) {
        humanizedContent.text('Request failed: ' + _error2);
        humanizedContent.parent().css('border-color', '#d63638');
        statsContent.text('Network error or server issue');
        resultsDiv.show();
      },
      complete: function complete() {
        // Re-enable button
        button.prop('disabled', false).text('Test Humanizer');
      }
    });
  }); // Add spinning animation for dashicons

  $('<style>').text("\n        .dashicons.spin {\n            animation: spin 1s linear infinite;\n        }\n        \n        @keyframes spin {\n            from { transform: rotate(0deg); }\n            to { transform: rotate(360deg); }\n        }\n        \n        .aanp-progress {\n            position: relative;\n            overflow: hidden;\n        }\n        \n        .aanp-progress-bar {\n            transition: width 0.3s ease;\n        }\n        \n        .button.disabled {\n            pointer-events: none;\n        }\n        \n        #humanizer-test-results {\n            transition: opacity 0.3s ease;\n        }\n        \n        #humanizer-test-results.show {\n            opacity: 1;\n        }\n    ").appendTo('head');
});