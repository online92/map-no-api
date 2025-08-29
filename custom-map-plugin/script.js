document.addEventListener("DOMContentLoaded", function () {
    const mapContainer = document.getElementById("map");
    const locationItems = document.querySelectorAll(".location-item");
    const phoneLinks = document.querySelectorAll(".location-phone a");
    const directionsLinks = document.querySelectorAll(".directions-btn");

    // Function to update the map iframe with location data
    function updateMap(lat, lng, name) {
        // Use encoded name for the marker label
        const encodedName = encodeURIComponent(name);
        mapContainer.innerHTML = `<iframe width="100%" height="400" src="https://www.google.com/maps?q=${lat},${lng}&hl=vi&z=15&output=embed"></iframe>`;
    }

    // Add click event listeners to location items
    locationItems.forEach((item, index) => {
        const lat = item.dataset.lat;
        const lng = item.dataset.lng;
        const name = item.dataset.name;

        item.addEventListener("click", function() {
            // Remove active class from all items
            locationItems.forEach(item => item.classList.remove("active"));
            
            // Add active class to clicked item
            this.classList.add("active");
            
            // Update map
            updateMap(lat, lng, name);
        });

        // Default display for the first location
        if (index === 0) {
            item.classList.add("active");
            updateMap(lat, lng, name);
        }
    });

    // Prevent phone links and direction buttons from triggering location item click
    phoneLinks.forEach((link) => {
        link.addEventListener("click", function(e) {
            // Stop event propagation to prevent triggering the location item click
            e.stopPropagation();
            // The default action (calling the phone number) will still happen
        });
    });

    directionsLinks.forEach((link) => {
        link.addEventListener("click", function(e) {
            // Stop event propagation to prevent triggering the location item click
            e.stopPropagation();
            // The default action (opening the map link) will still happen
        });
    });
});