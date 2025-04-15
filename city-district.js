jQuery(document).ready(function($) {
    if (typeof city_district_data === 'undefined') {
        console.error('city_district_data is undefined');
        return;
    }

    // Initialize Select2 on city and district selectors
    function initializeSelects() {
        $('.city-select, .district-select').select2(
            {
                maximumSelectionSize: 1
            }
        );
    }
// Add this function to your code
    function fixMultipleSelections() {
        $('.select2-results__option').attr('aria-selected', 'false');
        $('.select2-results__option.select2-results__option--highlighted').attr('aria-selected', 'true');
    }

// Call it after select2 is opened
    $(document).on('select2:open', '.city-select', function() {
        setTimeout(fixMultipleSelections, 100);
    });
    // Initialize on page load
//     initializeSelects();

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
    // Re-initialize after form updates
    $(document.body).on('updated_checkout', function() {
        // Destroy existing select2 instances first
        $('.city-select, .district-select').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });

        // Re-initialize
        populateCities();
        initializeSelects();
    });
    $(document).on('select2:rendering', '.city-select', function() {
        // Ensure only one item is selected
        setTimeout(function() {
            $('.select2-results__option[aria-selected="true"]').not(':first').attr('aria-selected', 'false');
        }, 0);
    });
    // Debugging: Log the data structure
    console.log('city_district_data:', city_district_data);
});