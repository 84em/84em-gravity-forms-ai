/**
 * 84EM Gravity Forms AI Analysis - Admin Scripts
 */

(function ( $ ) {
    'use strict';

    $( document ).ready( function () {

        // Tab Navigation
        $( '.nav-tab' ).on( 'click', function ( e ) {
            e.preventDefault();

            var target = $( this ).attr( 'href' );

            // Update active tab
            $( '.nav-tab' ).removeClass( 'nav-tab-active' );
            $( this ).addClass( 'nav-tab-active' );

            // Show target content
            $( '.tab-content' ).hide();
            $( target ).show();

            // Update URL hash
            window.location.hash = target;
        } );

        // Show initial tab based on hash
        if ( window.location.hash ) {
            $( '.nav-tab[href="' + window.location.hash + '"]' ).trigger( 'click' );
        }

        // Save Form Mapping
        $( '.save-mapping' ).on( 'click', function () {
            var button = $( this );
            var container = button.closest( '.form-mapping' );
            var formId = container.data( 'form-id' );
            var spinner = container.find( '.spinner' );
            var message = container.find( '.save-message' );

            // Collect selected fields
            var fields = [];
            container.find( '.field-selector:checked' ).each( function () {
                fields.push( $( this ).val() );
            } );

            // Get other settings
            var enabledOverride = container.find( '.form-enabled-override' ).val();
            var enabled = enabledOverride === '' ? null : parseInt(enabledOverride);
            var prompt = container.find( '.form-prompt' ).val();

            // Show loading state
            button.prop( 'disabled', true );
            spinner.addClass( 'is-active' );
            message.text( window['eightyfourGfAi'].strings.saving ).removeClass( 'success error' );

            // Make AJAX request
            $.post( window['eightyfourGfAi'].ajax_url, {
                action  : '84em_gf_ai_save_field_mapping',
                form_id : formId,
                fields  : fields,
                enabled : enabled,
                prompt  : prompt,
                nonce   : window['eightyfourGfAi'].nonce
            }, function ( response ) {
                spinner.removeClass( 'is-active' );
                button.prop( 'disabled', false );

                if ( response.success ) {
                    message.text( window['eightyfourGfAi'].strings.saved ).addClass( 'success' );
                }
                else {
                    message.text( response.data.message || window['eightyfourGfAi'].strings.error ).addClass( 'error' );
                }

                // Clear message after 3 seconds
                setTimeout( function () {
                    message.text( '' ).removeClass( 'success error' );
                }, 3000 );
            } ).fail( function () {
                spinner.removeClass( 'is-active' );
                button.prop( 'disabled', false );
                message.text( window['eightyfourGfAi'].strings.error ).addClass( 'error' );
            } );
        } );

        // Test API Connection
        $( '#test-api' ).on( 'click', function () {
            var button = $( this );
            var originalText = button.text();

            button.prop( 'disabled', true ).text( window['eightyfourGfAi'].strings.testing );

            $.post( window['eightyfourGfAi'].ajax_url, {
                action : '84em_gf_ai_test_api',
                nonce  : window['eightyfourGfAi'].nonce
            }, function ( response ) {
                button.prop( 'disabled', false ).text( originalText );

                if ( response.success ) {
                    alert( window['eightyfourGfAi'].strings.test_success );
                }
                else {
                    alert( window['eightyfourGfAi'].strings.test_failed + '\n\n' + (response.data.message || '') );
                }
            } ).fail( function () {
                button.prop( 'disabled', false ).text( originalText );
                alert( window['eightyfourGfAi'].strings.test_failed );
            } );
        } );

        // Select All / None for field checkboxes
        $( '.field-checkboxes' ).each( function () {
            var container = $( this );
            var controls = $( '<div class="field-controls" style="margin-bottom: 10px;">' +
                '<a href="#" class="select-all">Select All</a> | ' +
                '<a href="#" class="select-none">Select None</a>' +
                '</div>' );

            container.before( controls );

            controls.find( '.select-all' ).on( 'click', function ( e ) {
                e.preventDefault();
                container.find( 'input[type="checkbox"]' ).prop( 'checked', true );
            } );

            controls.find( '.select-none' ).on( 'click', function ( e ) {
                e.preventDefault();
                container.find( 'input[type="checkbox"]' ).prop( 'checked', false );
            } );
        } );

        // View Log Details
        $( '.view-log-details' ).on( 'click', function () {
            var logId = $( this ).data( 'log-id' );
            var modal = $( '#log-details-modal' );
            var content = modal.find( '.log-details-content' );

            // Get log details via AJAX
            $.post( window['eightyfourGfAi'].ajax_url, {
                action : '84em_gf_ai_get_log_details',
                log_id : logId,
                nonce  : window['eightyfourGfAi'].nonce
            }, function ( response ) {
                if ( response.success ) {
                    content.html( response.data.details );
                    modal.show();
                }
                else {
                    alert( 'Failed to load log details' );
                }
            } );
        } );

        // Close modal on click outside
        $( document ).on( 'click', function ( e ) {
            if ( $( e.target ).is( '#log-details-modal' ) ) {
                $( '#log-details-modal' ).hide();
            }
        } );

        // Close modal on ESC key
        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) {
                $( '#log-details-modal' ).hide();
            }
        } );

        // Auto-save settings when checkboxes change
        var saveTimeout;
        $( '.form-enabled-override, .field-selector' ).on( 'change', function () {
            var container = $( this ).closest( '.form-mapping' );
            var message = container.find( '.save-message' );

            // Clear previous timeout
            clearTimeout( saveTimeout );

            // Show pending message
            message.text( 'Changes pending...' ).removeClass( 'success error' );

            // Auto-save after 2 seconds of no changes
            saveTimeout = setTimeout( function () {
                container.find( '.save-mapping' ).trigger( 'click' );
            }, 2000 );
        } );

        // Prompt character counter
        $( '.form-prompt, #84em_gf_ai_default_prompt' ).each( function () {
            var textarea = $( this );
            var counter = $( '<div class="char-counter" style="text-align: right; color: #666; font-size: 12px; margin-top: 5px;"></div>' );
            textarea.after( counter );

            function updateCounter () {
                var length = textarea.val().length;
                counter.text( length + ' characters' );
            }

            textarea.on( 'input', updateCounter );
            updateCounter();
        } );

        // Confirm before clearing logs
        $( 'button[name="clear_logs"]' ).on( 'click', function ( e ) {
            if ( !confirm( 'Are you sure you want to clear all logs? This action cannot be undone.' ) ) {
                e.preventDefault();
            }
        } );

        // Copy to clipboard functionality for API responses
        $( document ).on( 'click', '.copy-response', function () {
            var button = $( this );
            var text = button.data( 'text' );

            // Create temporary textarea
            var temp = $( '<textarea>' );
            $( 'body' ).append( temp );
            temp.val( text ).select();
            document.execCommand( 'copy' );
            temp.remove();

            // Show feedback
            var originalText = button.text();
            button.text( 'Copied!' );
            setTimeout( function () {
                button.text( originalText );
            }, 2000 );
        } );

        // Syntax highlighting for JSON in log details
        if ( typeof Prism !== 'undefined' ) {
            $( '.log-details-content pre code' ).each( function () {
                Prism.highlightElement( this );
            } );
        }

    } );

})( jQuery );
