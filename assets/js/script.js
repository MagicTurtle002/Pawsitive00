$(document).ready(function () {
    $(".status-dropdown").change(function () {
        var appointmentId = $(this).data("id");
        var newStatus = $(this).val();
        var dropdown = $(this);

        $.ajax({
            url: "../../src/update_status.php",
            type: "POST",
            data: { appointmentId: appointmentId, status: newStatus },
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    Swal.fire("Updated!", "Appointment status updated successfully.", "success");
                } else {
                    Swal.fire("Error!", "Failed to update status.", "error");
                    dropdown.val(response.previousStatus);
                }
            },
            error: function () {
                Swal.fire("Error!", "An error occurred while updating.", "error");
                dropdown.val(response.previousStatus);
            }
        });
    });
});