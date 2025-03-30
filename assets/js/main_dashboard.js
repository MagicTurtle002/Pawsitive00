let currentView = 'month';
let appointmentsChart = null;
let petsChart = null;

// ========================
// 📊 Render Bar Chart
// ========================
document.addEventListener('DOMContentLoaded', function () {
    const chartElement = document.getElementById('appointmentsChart');
    const appointmentData = JSON.parse(chartElement.dataset.appointments);
    let currentChart = null;

    function renderBarChart(range) {
        const data = appointmentData[range];

        if (currentChart) currentChart.destroy();

        currentChart = new Chart(chartElement, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: `Appointments Per ${range.charAt(0).toUpperCase() + range.slice(1)}`,
                    data: data.counts,
                    backgroundColor: '#a8ebf0',
                    borderColor: '#156f77',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (tooltipItem) => `Appointments: ${tooltipItem.raw}`
                        }
                    },
                    legend: { display: false }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Date Range' },
                        ticks: { maxRotation: 45, minRotation: 45 }
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Appointments' }
                    }
                }
            }
        });
    }

    renderBarChart('month');
    document.getElementById('appointmentRange').addEventListener('change', (event) => {
        renderBarChart(event.target.value);
    });
});

// ========================
// 📊 Render Species Pie Chart
// ========================
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('speciesPieChart').getContext('2d');
    const allLabels = JSON.parse(ctx.canvas.dataset.labels);
    const allCounts = JSON.parse(ctx.canvas.dataset.counts);
    const speciesColors = {
        "Dog": "#FF6384", "Cat": "#36A2EB", "Rabbit": "#FFCE56", "Bird": "#4CAF50",
        "Hamster": "#9C27B0", "Guinea Pig": "#FF9800", "Reptile": "#795548",
        "Ferret": "#03A9F4", "Fish": "#E91E63"
    };
    const assignedColors = allLabels.map(species => speciesColors[species] || "#CCCCCC");

    let speciesChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: allLabels,
            datasets: [{ data: allCounts, backgroundColor: assignedColors, borderColor: '#fff', borderWidth: 2 }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right', labels: { color: '#4A4A4A', font: { size: 14, family: 'Poppins' } } },
                tooltip: {
                    callbacks: {
                        label: (tooltipItem) => `${speciesChart.data.labels[tooltipItem.dataIndex]}: ${speciesChart.data.datasets[0].data[tooltipItem.dataIndex]} pets`
                    }
                }
            }
        }
    });

    document.getElementById('speciesFilter').addEventListener('change', function () {
        const selectedSpecies = this.value;
        if (selectedSpecies === 'all') {
            speciesChart.data.labels = allLabels;
            speciesChart.data.datasets[0].data = allCounts;
            speciesChart.data.datasets[0].backgroundColor = assignedColors;
        } else {
            const index = allLabels.indexOf(selectedSpecies);
            if (index !== -1) {
                speciesChart.data.labels = [allLabels[index]];
                speciesChart.data.datasets[0].data = [allCounts[index]];
                speciesChart.data.datasets[0].backgroundColor = [speciesColors[selectedSpecies]];
            }
        }
        speciesChart.update();
    });
});

// ========================
// 📥 Export PDF
// ========================
async function exportAllDataPDF() {
    console.log("Exporting PDF...");

    Swal.fire({
        title: "Exporting PDF...",
        html: `<div class="swal2-loading-container"><div class="swal2-spinner"></div><p>Generating report...</p></div>`,
        allowOutsideClick: false,
        showConfirmButton: false
    });

    try {
        const url = '../src/export_dashboard_pdf.php';
        const response = await fetch(url, { method: 'POST' });

        if (!response.ok) throw new Error(`Server error: ${response.status}`);

        const blob = await response.blob();
        const downloadUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = `dashboard_report_${new Date().toISOString().split('T')[0]}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(downloadUrl);

        Swal.fire("Success", "PDF report exported successfully!", "success");
    } catch (error) {
        console.error("Export error:", error);
        Swal.fire("Error", "Failed to export PDF. Please try again.", "error");
    }
}

// Ensure export function is globally accessible
window.exportAllDataPDF = exportAllDataPDF;

function promptVitalsUpdate(appointmentId, petId) {
    // ✅ Check sessionStorage before prompting the user
    if (sessionStorage.getItem(`vitals_${appointmentId}_${petId}`) === 'recorded') {
        console.log('Skipping vitals prompt: already recorded.');

        Swal.fire({
            title: 'Weight Already Recorded!',
            text: 'Redirecting to patient records...',
            icon: 'info',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = `patient_records.php?appointment_id=${appointmentId}&pet_id=${petId}`;
        });

        return;
    }

    // ✅ Fetch from the server if vitals exist
    fetch(`../src/check_vitals_status.php?appointment_id=${appointmentId}&pet_id=${petId}`)
        .then(response => response.json())
        .then(data => {
            if (data.alreadyRecorded) {
                // ✅ If already recorded, store in sessionStorage and skip the prompt
                sessionStorage.setItem(`vitals_${appointmentId}_${petId}`, 'recorded');
                console.log('Skipping vitals prompt from database check.');
                window.location.href = `patient_records.php?appointment_id=${appointmentId}&pet_id=${petId}`;
            } else {
                // ✅ Show the prompt only if vitals are NOT recorded
                showVitalsPrompt(appointmentId, petId);
            }
        })
        .catch(error => {
            console.error('Error checking vitals:', error);
            showVitalsPrompt(appointmentId, petId);
        });
}

function showVitalsPrompt(appointmentId, petId) {
    Swal.fire({
        title: 'Update Pet Vitals',
        html: `
            <button class="swal2-close-button" onclick="Swal.close()">×</button>

            <div class="swal2-row">
                <label for="weight">Weight (kg):<span class="required">*</span></label>
                <input type="number" id="weight" class="swal2-input" placeholder="Enter weight in kg" min="0.1" step="0.1">
            </div>
            
            <div class="swal2-row">
                <label for="temperature">Temp (°C):<span class="required">*</span></label>
                <input type="number" id="temperature" class="swal2-input" placeholder="Enter temperature in °C">
            </div>
        `,
        confirmButtonText: 'Update & Start',
        preConfirm: () => {
            const weight = parseFloat(document.getElementById('weight').value);
            const temperature = parseFloat(document.getElementById('temperature').value);

            if (!weight || !temperature || weight <= 0) {
                Swal.showValidationMessage('All fields are required and must be valid.');
                return false;
            }

            // Check for special case temperatures
            if (temperature < 30 || temperature > 45) {
                return Swal.fire({
                    title: 'Unusual Temperature',
                    text: `The temperature you entered (${temperature}°C) is unusual. Do you want to proceed?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Proceed',
                    cancelButtonText: 'No, Re-enter'
                }).then((confirmResult) => {
                    if (confirmResult.isConfirmed) {
                        return { weight, temperature }; // Allow unusual value
                    } else {
                        return false; // Force re-entry
                    }
                });
            }

            return { weight, temperature }; // Normal case
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            updateVitalsAndStartConsultation(appointmentId, petId, result.value.weight, result.value.temperature);
        }
    });
}

function updateVitalsAndStartConsultation(appointmentId, petId, weight, temperature) {
    fetch('../src/update_pet_vitals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ appointment_id: appointmentId, pet_id: petId, weight, temperature })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Server Response:', data);

        if (data.success) {
            sessionStorage.setItem(`vitals_${appointmentId}_${petId}`, 'recorded');
            handleVitalsSuccess(appointmentId, petId);
        } else {
            Swal.fire('Error', data.message || 'Update failed.', 'error');
        }
    })
    .catch(error => console.error('Fetch Error:', error));
}

function handleVitalsSuccess(appointmentId, petId) {
    Swal.fire({
        title: 'Vitals Updated!',
        text: 'Redirecting to patient records...',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    }).then(() => {
        window.location.href = `patient_records.php?appointment_id=${appointmentId}&pet_id=${petId}`;
    });
}

function promptVitalsVaccine(appointmentId, petId) {
    // ✅ Check sessionStorage before prompting the user
    if (sessionStorage.getItem(`vaccine_vitals_${appointmentId}_${petId}`) === 'recorded') {
        console.log('Skipping weight prompt: already recorded.');
        Swal.fire({
            title: "Pet's weight is already updated!",
            text: "Redirecting to vaccination records...",
            icon: "info",
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = `add_vaccine.php?appointment_id=${appointmentId}&pet_id=${petId}`;
        });
        return;
    }

    // ✅ Fetch from the server if weight was already recorded
    fetch(`../src/check_vaccine_status.php?appointment_id=${appointmentId}&pet_id=${petId}`)
        .then(response => response.json())
        .then(data => {
            if (data.alreadyRecorded) {
                // ✅ If already recorded, store in sessionStorage and skip the prompt
                sessionStorage.setItem(`vaccine_vitals_${appointmentId}_${petId}`, 'recorded');
                console.log('Skipping weight prompt from database check.');

                Swal.fire({
                    title: "Pet's weight is already updated!",
                    text: "Redirecting to vaccination records...",
                    icon: "info",
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = `add_vaccine.php?appointment_id=${appointmentId}&pet_id=${petId}`;
                });
            } else {
                // ✅ Show the prompt only if weight is NOT recorded
                showVaccinePrompt(appointmentId, petId);
            }
        })
        .catch(error => {
            console.error('Error checking weight:', error);
            showVaccinePrompt(appointmentId, petId);
        });
}

function showVaccinePrompt(appointmentId, petId) {
    Swal.fire({
        title: 'Update Pet Weight',
        html: `
            <button class="swal2-close-button" onclick="Swal.close()">×</button>

            <div class="swal2-row">
                <label for="weight">Weight (kg):<span class="required">*</span></label>
                <input type="number" id="weight" class="swal2-input" placeholder="Enter weight in kg" min="0.1" step="0.1">
            </div>
        `,
        confirmButtonText: 'Update & Start',
        preConfirm: () => {
            const weight = document.getElementById('weight').value.trim();
            if (!weight || isNaN(weight) || weight <= 0 || !/^\d*\.?\d+$/.test(weight)) {
                Swal.showValidationMessage('Weight must be a positive number without letters or symbols.');
                return false;
            }
            
            return { weight: parseFloat(weight) }; 
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateWeightAndStartVaccination(appointmentId, petId, result.value.weight);
        }
    });
}

window.promptVitalsVaccine = promptVitalsVaccine;

function updateWeightAndStartVaccination(appointmentId, petId, weight) {
    fetch('../src/update_pet_weight.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ appointment_id: appointmentId, pet_id: petId, weight })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Server Response:', data);

        if (data.success) {
            // ✅ Store that weight is already recorded
            sessionStorage.setItem(`vaccine_vitals_${appointmentId}_${petId}`, 'recorded');

            Swal.fire({
                title: "Weight updated!",
                text: "Redirecting to vaccination records...",
                icon: "success",
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = `add_vaccine.php?appointment_id=${appointmentId}&pet_id=${petId}`;
            });
        } else {
            if (data.alreadyRecorded) {
                sessionStorage.setItem(`vaccine_vitals_${appointmentId}_${petId}`, 'recorded');
                Swal.fire({
                    title: "Pet's weight is already updated!",
                    text: "Redirecting to vaccination records...",
                    icon: "info",
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = `add_vaccine.php?appointment_id=${appointmentId}&pet_id=${petId}`;
                });
            } else {
                Swal.fire('Error', data.message || 'Update failed.', 'error');
            }
        }
    })
    .catch(error => console.error('Fetch Error:', error));
}

// ========================
// 🧾 Generate Invoice
// ========================
function generateInvoice(appointmentId, petId) {
    if (!appointmentId || !petId) return console.error("❌ Missing appointment or pet ID.");
    Swal.fire({ title: "Generate Invoice?", text: "Are you sure you want to generate an invoice for this appointment?", icon: "question", showCancelButton: true, confirmButtonText: "Yes, Generate", cancelButtonText: "Cancel" })
        .then((result) => {
            if (result.isConfirmed) {
                fetch('../src/generate_invoice.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ appointment_id: appointmentId, pet_id: petId }) })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire("Success", "Invoice generated successfully!", "success")
                                .then(() => { window.location.href = 'invoice_billing_form.php'; });
                        } else {
                            Swal.fire("Error", data.message, "error");
                        }
                    })
                    .catch(error => console.error("❌ Error:", error));
            }
        });
}
window.generateInvoice = generateInvoice;