document.addEventListener("DOMContentLoaded", function () {
    const mapContainer = document.getElementById("map");
    if (!mapContainer) return; // Thoát nếu không tìm thấy map container

    const locationItems = document.querySelectorAll(".location-item");

    // --- PHẦN QUAN TRỌNG NHẤT ĐỂ HIỂN THỊ TÊN ---
    // Chức năng cập nhật iframe của bản đồ
    function updateMap(lat, lng, name) {
        // Mã hóa tên địa điểm để sử dụng trong URL
        const encodedName = encodeURIComponent(name);
        
        // URL này sử dụng tham số 'q' để Google Maps đặt một điểm đánh dấu (marker) kèm theo nhãn là tên địa điểm
        const mapUrl = `https://maps.google.com/maps?q=${lat},${lng}(${encodedName})&z=15&output=embed&hl=vi`;
        
        mapContainer.innerHTML = `<iframe width="100%" height="100%" style="border:0;" loading="lazy" src="${mapUrl}"></iframe>`;
    }

    // Gán sự kiện click cho từng địa điểm
    locationItems.forEach((item, index) => {
        const lat = item.dataset.lat;
        const lng = item.dataset.lng;
        const name = item.dataset.name;

        // Bỏ qua nếu không có đủ dữ liệu
        if (!lat || !lng || !name) return;

        item.addEventListener("click", function(e) {
            // Ngăn chặn sự kiện click nếu người dùng nhấn vào link điện thoại hoặc chỉ đường
            if (e.target.closest('a')) {
                return;
            }

            locationItems.forEach(i => i.classList.remove("active"));
            this.classList.add("active");
            updateMap(lat, lng, name);
        });

        // Tự động hiển thị địa điểm đầu tiên khi tải trang
        if (index === 0) {
            item.classList.add("active");
            updateMap(lat, lng, name);
        }
    });

    // Kích hoạt color picker trong trang admin (nếu có)
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.wpColorPicker !== 'undefined') {
        jQuery(document).ready(function($){
            $('.color-picker').wpColorPicker();
        });
    }
});