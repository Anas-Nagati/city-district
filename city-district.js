jQuery(document).ready(function($) {
    if (typeof city_district_data === 'undefined') {
        console.error('city_district_data is undefined');
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

    // Handle city selection changes
    $(document).on('select2:select', '.city-select', function(e) {
        var cityId = e.params.data.id;
        if (!cityId) {
            console.error('City ID is undefined');
            return;
        }

        // Find the corresponding district select
        var districtSelect;
        if ($(this).is('#billing_city_field')) {
            districtSelect = $('#billing_address_1_field');
        } else if ($(this).is('#shipping_city')) {
            districtSelect = $('#shipping_address_1_field');
        } else {
            console.error('City select element not found');
            return;
        }

        // Clear existing districts
        districtSelect.find('option:not(:first)').remove();
        districtSelect.val('').trigger('change');

        // Get districts from preloaded data
        if (city_district_data.districts_by_city && city_district_data.districts_by_city[cityId]) {
            var districts = city_district_data.districts_by_city[cityId];
            $.each(districts, function(index, district) {
                districtSelect.append(`<option value="${district.id}">${district.name}</option>`);
            });
            districtSelect.trigger('change');
        } else {
            console.error('No districts found for city ID:', cityId);
        }
    });

    // Re-initialize after form updates
    $(document.body).on('updated_checkout', function() {
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

    // Debugging: Log the data structure
    console.log('city_district_data:', city_district_data);
});
