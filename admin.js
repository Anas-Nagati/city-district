jQuery(document).ready(function($) {
    // Add city form submission
    $('#add-city-form').on('submit', function(e) {
        e.preventDefault();

        var cityName = $('#city-name').val();

        $.ajax({
            url: city_district_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'add_city',
                city_name: cityName,
                nonce: city_district_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#city-message').html('<div class="notice notice-success inline"><p>City added successfully!</p></div>');

                    // Add city to the table
                    $('table tbody').first().append(`
                        <tr>
                            <td>${response.data.id}</td>
                            <td>${response.data.name}</td>
                            <td>
                                <button class="button delete-location" data-id="${response.data.id}">Delete</button>
                            </td>
                        </tr>
                    `);

                    // Add city to dropdown
                    $('#city-parent').append(`<option value="${response.data.id}">${response.data.name}</option>`);

                    // Clear form
                    $('#city-name').val('');
                } else {
                    $('#city-message').html(`<div class="notice notice-error inline"><p>${response.data}</p></div>`);
                }

                // Clear message after 3 seconds
                setTimeout(function() {
                    $('#city-message').html('');
                }, 3000);
            }
        });
    });

    // Add district form submission
    $('#add-district-form').on('submit', function(e) {
        e.preventDefault();

        var cityId = $('#city-parent').val();
        var districtName = $('#district-name').val();

        $.ajax({
            url: city_district_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'add_district',
                city_id: cityId,
                district_name: districtName,
                nonce: city_district_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#district-message').html('<div class="notice notice-success inline"><p>District added successfully!</p></div>');

                    // Add district to the table
                    $('table tbody').eq(1).append(`
                        <tr>
                            <td>${response.data.id}</td>
                            <td>${response.data.name}</td>
                            <td>${response.data.city_name}</td>
                            <td>${response.data.city_id}</td>
                            <td>
                                <button class="button delete-location" data-id="${response.data.id}">Delete</button>
                            </td>
                        </tr>
                    `);

                    // Clear form
                    $('#district-name').val('');
                } else {
                    $('#district-message').html(`<div class="notice notice-error inline"><p>${response.data}</p></div>`);
                }

                // Clear message after 3 seconds
                setTimeout(function() {
                    $('#district-message').html('');
                }, 3000);
            }
        });
    });

    // Delete location
    $(document).on('click', '.delete-location', function() {
        if (!confirm('Are you sure you want to delete this location?')) {
            return;
        }

        var button = $(this);
        var locationId = button.data('id');

        $.ajax({
            url: city_district_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_location',
                location_id: locationId,
                nonce: city_district_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data);
                }
            }
        });
    });
});