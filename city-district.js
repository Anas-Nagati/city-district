jQuery(document).ready(function($) {
    if (typeof city_district_data === 'undefined') {
        return;
    }

    // Initialize Select2 on city selectors
    function initializeSelects() {
        $('.city-select').select2({
            placeholder: 'Select a city',
            allowClear: true,
            width: '100%'
        });

        $('.district-select').select2({
            placeholder: 'Select a district',
            allowClear: true,
            width: '100%'
        });
    }

    // Initialize on page load
    initializeSelects();

    // Populate cities on page load
    function populateCities() {
        var cities = city_district_data.cities;
        $('.city-select').each(function() {
            var select = $(this);
            var currentVal = select.val();

            // Check if options are already added (to avoid duplicates)
            if (select.find('option').length <= 1) {
                // Add options
                $.each(cities, function(index, city) {
                    select.append(`<option value="${city.id}">${city.name}</option>`);
                });
            }

            // Restore selected value if any
            if (currentVal) {
                select.val(currentVal);
            }

            // Trigger change to update Select2
            select.trigger('change');
        });
    }

    // Populate cities on page load
    populateCities();

    // Use select2:select event instead of change for more reliable behavior
    $(document).on('select2:select', '#billing_city_field', function(e) {
        var cityId = e.params.data.id;
        console.log("City selected via Select2:", cityId, e.params.data.text);

        if (!cityId) {
            return;
        }

        var districtSelect = $('#billing_address_1_field');
        console.log("Target district field:", districtSelect.length ? "Found" : "Not found");

        // Clear districts
        districtSelect.find('option:not(:first)').remove();
        districtSelect.val('').trigger('change');

        // Show loading
        districtSelect.prop('disabled', true);

        // Get districts for the selected city
        $.ajax({
            url: city_district_data.ajax_url,
            type: 'POST',
            data: {
                action: 'get_districts',
                city_id: cityId,
                nonce: city_district_data.nonce
            },
            success: function(response) {
                console.log("AJAX response:", response);

                if (response.success && response.data && response.data.length > 0) {
                    console.log("All districts for selected city:", response.data); // 👈 ADD THIS LINE

                    $.each(response.data, function(index, district) {
                        districtSelect.append(`<option value="${district.id}">${district.name}</option>`);
                    });

                    districtSelect.trigger('change');
                } else {
                    console.error("No districts returned or error:", response);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", status, error);
            },
            complete: function() {
                districtSelect.prop('disabled', false);
            }
        });
    });

    // Also handle shipping city changes with the same approach
    $(document).on('select2:select', '#shipping_city', function(e) {
        var cityId = e.params.data.id;
        console.log("Shipping city selected:", cityId, e.params.data.text);

        if (!cityId) {
            return;
        }

        var districtSelect = $('#shipping_address_1');

        // Clear districts
        districtSelect.find('option:not(:first)').remove();
        districtSelect.val('').trigger('change');

        districtSelect.prop('disabled', true);

        $.ajax({
            url: city_district_data.ajax_url,
            type: 'POST',
            data: {
                action: 'get_districts',
                city_id: cityId,
                nonce: city_district_data.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    $.each(response.data, function(index, district) {
                        districtSelect.append(`<option value="${district.id}">${district.name}</option>`);
                    });
                    districtSelect.trigger('change');
                }
            },
            complete: function() {
                districtSelect.prop('disabled', false);
            }
        });
    });

    // Re-initialize after form updates
    $(document.body).on('updated_checkout', function() {
        console.log("Checkout updated, reinitializing...");

        setTimeout(function() {
            // Destroy existing select2 instances first
            $('.city-select, .district-select').each(function() {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
            });

            // Re-initialize
            initializeSelects();
            populateCities();
        }, 200);
    });
});