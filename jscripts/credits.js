var Credits = {
    getBalance: function(callback) {
        $.ajax({
            url: 'xmlhttp.php',
            type: 'POST',
            data: {
                action: 'credits',
                operation: 'get_balance',
                my_post_key: my_post_key
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && typeof callback === 'function') {
                    callback(response.balance);
                }
            }
        });
    },

    refreshNavBalance: function() {
        Credits.getBalance(function(balance) {
            $('.credits_nav_link').each(function() {
                var text = $(this).text();
                $(this).text(text.replace(/\(\d+\)/, '(' + balance + ')'));
            });
        });
    },

    inventoryToggle: function(button) {
        var $btn = $(button);
        if ($btn.prop('disabled')) {
            return;
        }

        var confirmMsg = $btn.data('confirm');
        if (confirmMsg && !confirm(confirmMsg)) {
            return;
        }

        var pid = $btn.data('pid');
        var action = $btn.data('action');
        var postKey = $btn.data('postkey');

        $btn.prop('disabled', true).css('opacity', '0.5');

        $.ajax({
            url: 'xmlhttp.php',
            type: 'POST',
            data: {
                action: 'credits',
                operation: 'inventory_toggle',
                pid: pid,
                toggle_action: action,
                my_post_key: postKey
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.error || 'An error occurred.');
                    $btn.prop('disabled', false).css('opacity', '1');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).css('opacity', '1');
            }
        });
    },

    inventoryEdit: function(button) {
        var $btn = $(button);
        if ($btn.prop('disabled')) {
            return;
        }

        var editType = $btn.data('edit-type');
        var current = $btn.data('current');
        var pid = $btn.data('pid');
        var postKey = $btn.data('postkey');
        var newValue;

        if (editType === 'color') {
            var $input = $('<input type="color" style="position:absolute;visibility:hidden;">');
            $input.val(current || '#000000');
            $('body').append($input);
            $input.on('change', function() {
                newValue = $(this).val();
                $input.remove();
                if (newValue && newValue !== current) {
                    Credits._sendEdit(pid, newValue, postKey, $btn);
                }
            });
            $input.trigger('click');
        } else {
            newValue = prompt('Enter new value:', current || '');
            if (newValue !== null && newValue.trim() !== '' && newValue !== current) {
                Credits._sendEdit(pid, newValue.trim(), postKey, $btn);
            }
        }
    },

    _sendEdit: function(pid, newValue, postKey, $btn) {
        $btn.prop('disabled', true).css('opacity', '0.5');

        $.ajax({
            url: 'xmlhttp.php',
            type: 'POST',
            data: {
                action: 'credits',
                operation: 'inventory_edit',
                pid: pid,
                new_value: newValue,
                my_post_key: postKey
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.error || 'An error occurred.');
                    $btn.prop('disabled', false).css('opacity', '1');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).css('opacity', '1');
            }
        });
    },

    initInventory: function() {
        $(document).on('click', '.credits_inv_action', function(e) {
            e.preventDefault();
            Credits.inventoryToggle(this);
        });
        $(document).on('click', '.credits_inv_edit', function(e) {
            e.preventDefault();
            Credits.inventoryEdit(this);
        });
    },

    inventoryToggleCard: function(headerEl) {
        var $card = $(headerEl).closest('.credits_inv_card');
        $card.toggleClass('credits_inv_card_open credits_inv_card_collapsed');
    },

    shopSelectCategory: function(cid) {
        var $categoryBlock = $('.credits_shop_category_block[data-cid="' + cid + '"]');

        if ($categoryBlock.length) {
            $('.credits_shop_category_block').hide();
            $categoryBlock.show();
        } else {
            var $card = $('#inv_' + cid);
            if ($card.length) {
                if ($card.hasClass('credits_inv_card_collapsed')) {
                    $card.removeClass('credits_inv_card_collapsed').addClass('credits_inv_card_open');
                }
                $('html, body').animate({ scrollTop: $card.offset().top - 10 }, 300);
            }
        }

        $('.credits_shop_sidebar_link').removeClass('active');
        $('.credits_shop_sidebar_link[data-cid="' + cid + '"]').addClass('active');
    },

    initShop: function() {
        var $firstLink = $('.credits_shop_sidebar_link').first();
        if ($firstLink.length) {
            if ($('.credits_shop_category_block').length) {
                Credits.shopSelectCategory($firstLink.data('cid'));
            } else {
                $firstLink.addClass('active');
            }
        }
    },

    initColorPicker: function() {
        $('input[name="purchase_value"]').on('input', function() {
            if (this.type === 'color') {
                $('input[name="purchase_value_hex"]').val(this.value);
            }
        });

        $('input[name="purchase_value_hex"]').on('input', function() {
            var hex = $(this).val();
            if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
                $('input[name="purchase_value"][type="color"]').val(hex);
            }
        });
    }
};

$(function() {
    Credits.initColorPicker();
    Credits.initInventory();
    Credits.initShop();
});
