jQuery(document).ready(function ($) {

    // Handle add subdomain form submission
    $('#add-subdomain-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('input[type="submit"]');
        var originalText = $submitBtn.val();

        // Get form data
        var subdomain = $('#new_subdomain').val().trim();
        var domain = 'ai-web.site'; // Default domain

        if (!subdomain) {
            alert('Please enter a subdomain name');
            return;
        }

        // Validate subdomain format
        if (!/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/.test(subdomain)) {
            alert('Subdomain can only contain letters, numbers, and hyphens. It cannot start or end with a hyphen.');
            return;
        }

        // Show loading state
        $submitBtn.val(aiWebSite.strings.creating).prop('disabled', true);

        // Make AJAX request
        $.ajax({
            url: aiWebSite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_subdomain',
                nonce: aiWebSite.nonce,
                subdomain: subdomain,
                domain: domain
            },
            success: function (response) {
                if (response.success) {
                    // Show success message
                    showNotice('success', 'Subdomain created successfully: ' + subdomain + '.' + domain);

                    // Clear form
                    $('#new_subdomain').val('');

                    // Reload page to show new subdomain
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    showNotice('error', 'Error creating subdomain: ' + (response.data || 'Unknown error'));
                }
            },
            error: function () {
                showNotice('error', 'Network error occurred');
            },
            complete: function () {
                // Reset button
                $submitBtn.val(originalText).prop('disabled', false);
            }
        });
    });

    // Handle delete subdomain
    $(document).on('click', '.delete-subdomain', function (e) {
        e.preventDefault();

        if (!confirm(aiWebSite.strings.confirmDelete)) {
            return;
        }

        var $button = $(this);
        var subdomain = $button.data('subdomain');
        var domain = $button.data('domain');
        var originalText = $button.text();

        // Show loading state
        $button.text(aiWebSite.strings.deleting).prop('disabled', true);

        // Make AJAX request
        $.ajax({
            url: aiWebSite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_subdomain',
                nonce: aiWebSite.nonce,
                subdomain: subdomain,
                domain: domain
            },
            success: function (response) {
                if (response.success) {
                    // Show success message
                    showNotice('success', 'Subdomain deleted successfully');

                    // Remove row from table
                    $button.closest('tr').fadeOut(function () {
                        $(this).remove();
                    });
                } else {
                    // Show error message
                    showNotice('error', 'Error deleting subdomain: ' + (response.data || 'Unknown error'));
                }
            },
            error: function () {
                showNotice('error', 'Network error occurred');
            },
            complete: function () {
                // Reset button
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Handle test connection button
    $('input[name="test_connection"]').on('click', function (e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.val();

        // Show loading state
        $button.val(aiWebSite.strings.testing).prop('disabled', true);

        // Submit the form to test connection
        var $form = $button.closest('form');
        $form.attr('action', $form.find('input[name="action"][formaction]').attr('formaction'));
        $form.submit();
    });

    // Function to show admin notices
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

        // Insert notice after the page title
        $('.wrap h1').after(notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            notice.fadeOut();
        }, 5000);
    }

    // Handle form validation
    $('#new_subdomain').on('input', function () {
        var value = $(this).val();
        var $suffix = $('.domain-suffix');

        // Update domain suffix if main domain changes
        // This is handled by PHP, but we can add client-side validation here

        // Validate subdomain format in real-time
        if (value && !/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/.test(value)) {
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
        }
    });

    // Add CSS for error state
    $('<style>')
        .prop('type', 'text/css')
        .html('.error { border-color: #dc3232 !important; }')
        .appendTo('head');

});
