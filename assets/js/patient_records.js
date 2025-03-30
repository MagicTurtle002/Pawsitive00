document.addEventListener('DOMContentLoaded', function () {
    const lastVaccinationInput = document.getElementById("last_vaccination");
    const lastDewormingInput = document.getElementById("last_deworming");
    const lastHeatCycle = document.getElementById("LastHeatCycle");

    if (lastVaccinationInput) {
        const today = new Date().toISOString().split("T")[0]; // Get today's date in YYYY-MM-DD format
        lastVaccinationInput.setAttribute("max", today); // Set max attribute to prevent future dates
    }

    if (lastDewormingInput) {
        const today = new Date().toISOString().split("T")[0]; // Get today's date in YYYY-MM-DD format
        lastDewormingInput.setAttribute("max", today); // Set max attribute to prevent future dates
    }

    if (lastHeatCycle) {
        const today = new Date().toISOString().split("T")[0]; // Get today's date in YYYY-MM-DD format
        lastHeatCycle.setAttribute("max", today); // Set max attribute to prevent future dates
    }
    // =========================
    // Display Success Messages
    // =========================
    function showSuccessMessage(sessionKey) {
        if (sessionStorage.getItem(sessionKey)) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: sessionStorage.getItem(sessionKey),
                confirmButtonColor: '#156f77'
            });
            sessionStorage.removeItem(sessionKey);
        }
    }

    [
        'success_chief_complaint', 'success_medical_history', 'success_physical_exam', 
        'success_lab_exam', 'success_diagnosis'
    ].forEach(showSuccessMessage);

    // =========================
    // Display Error Messages
    // =========================
    if (typeof errors !== 'undefined' && errors.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error Occurred',
            html: errors.join('<br>'),
            confirmButtonColor: '#d33'
        });
    }

    // =========================
    // Confirm Finish Consultation
    // =========================
    document.querySelectorAll('.confirm-finish').forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            Swal.fire({
                title: 'Finish Consultation?',
                text: "All data will be saved, and you can't modify this appointment afterward.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, finish it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    event.target.form.submit();
                }
            });
        });
    });

    // =========================
    // Toggle Inputs for "Other" Selection
    // =========================
    function toggleOtherSpecify(selectId, inputId) {
        let select = document.getElementById(selectId);
        let input = document.getElementById(inputId);

        if (select) {
            select.addEventListener("change", function () {
                input.style.display = (this.value === "Other" || this.value === "Other (Specify)") ? "block" : "none";
                if (input.style.display === "none") input.value = "";
            });
        }
    }

    // Function for toggling "Other" checkbox inputs
    function toggleOtherCheckbox(checkboxId, inputId) {
        let checkbox = document.getElementById(checkboxId);
        let input = document.getElementById(inputId);

        if (checkbox && input) {
            checkbox.addEventListener("change", function () {
                input.style.display = this.checked ? "block" : "none";
                if (!this.checked) {
                    input.value = "";
                }
            });
        }
    }
    
    toggleOtherCheckbox("other_checkbox", "other_symptom");

    // Apply the function for other selects
    toggleOtherSpecify("diet", "custom-diet");
    toggleOtherSpecify("frequency", "custom-frequency");
    toggleOtherSpecify("color", "custom-color");
    toggleOtherSpecify("duration", "custom-duration");
    toggleOtherSpecify("Vaccine", "VaccineInput");
    toggleOtherSpecify("Dewormer", "DewormerInput");
    toggleOtherSpecify("pulse", "pulseInput");
    toggleOtherSpecify("heart-rate", "heartRateInput");
    toggleOtherSpecify("respiratory-rate", "respiratoryInput");
    toggleOtherSpecify("heart-sound", "heartSoundInput");
    toggleOtherSpecify("lung-sound", "lungSoundInput");
    toggleOtherSpecify("mucous-membrane", "mucousMembraneInput");
    toggleOtherSpecify("capillary-refill-time", "crtInput");
    toggleOtherSpecify("eyes", "eyesInput");
    toggleOtherSpecify("ears", "earsInput");
    toggleOtherSpecify("abdominal-palpation", "abdominalInput");
    toggleOtherSpecify("ln", "lnInput");

    // =========================
    // Medication Management
    // =========================
    const medicationsContainer = document.getElementById('medications-container');
    const addMedicationButton = document.getElementById('add-medication');

    function addMedicationField() {
        const medicationItem = medicationsContainer.firstElementChild.cloneNode(true);
        medicationItem.querySelectorAll('input, select').forEach(input => {
            input.value = '';
            input.style.display = (input.type === 'text') ? 'none' : '';
        });

        medicationItem.querySelector('.delete-button').style.display = 'inline-block';
        medicationItem.querySelector('.delete-button').addEventListener('click', () => medicationItem.remove());
        medicationsContainer.appendChild(medicationItem);
    }

    if (addMedicationButton) {
        addMedicationButton.addEventListener('click', addMedicationField);
    }

    medicationsContainer.querySelectorAll('.delete-button').forEach(button => {
        button.addEventListener('click', function () {
            if (medicationsContainer.children.length > 1) this.parentElement.remove();
        });
    });

    // =========================
    // Character Counter for Chief Complaint & Medication Given Prior to Check-Up
    // =========================
    function updateCharCount(textAreaId, counterId) {
        const textArea = document.getElementById(textAreaId);
        const counter = document.getElementById(counterId);
        if (textArea && counter) {
            textArea.addEventListener('input', () => {
                counter.textContent = `${textArea.value.length} / 300 characters`;
            });
            counter.textContent = `${textArea.value.length} / 300 characters`; // Initialize counter on page load
        }
    }

    updateCharCount('chief_complaint', 'chief-complaint-char-count');
    updateCharCount('medication', 'medication-char-count');
    updateCharCount('BehavioralIssues', 'BehavioralIssues-char-count');

    // =========================
    // Update Pain Level Value
    // =========================
    const painSlider = document.getElementById('pain_level');
    const painDisplay = document.getElementById('pain_value_display');
    if (painSlider && painDisplay) {
        painSlider.addEventListener('input', () => {
            painDisplay.textContent = painSlider.value;
        });
    }

    // =========================
    // Go Back Button
    // =========================
    document.querySelectorAll('.go-back').forEach(button => {
        button.addEventListener('click', function () {
            const petId = new URLSearchParams(window.location.search).get('pet_id');
            window.location.href = `../public/pet_profile.php?PetId=${petId}`;
        });
    });
});


