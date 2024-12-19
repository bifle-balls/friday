document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const registryDateInput = document.querySelector("#registryDate");
    const birthdayInput = document.querySelector("#birthday");
    const appointmentDateInput = document.querySelector("#appointmentDate"); // Assuming your Appointment Date has the ID 'appointmentDate'
    const startTimeInput = document.querySelector("#startTime");
    const endTimeInput = document.querySelector("#endTime");

    form.addEventListener("submit", function (event) {
        const today = new Date();

        // Normalize today's date for comparison (without time)
        today.setHours(0, 0, 0, 0);

        // Function to validate the year for date inputs
        function validateDateInput(dateInput) {
            const date = new Date(dateInput.value);
            const year = date.getFullYear();
            if (year < 1000 || year > 9999) {
                alert("Please enter a valid 4-digit year.");
                event.preventDefault(); // Prevent form submission
                return false;
            }
            return true;
        }

        // Validate all date inputs for 4-digit year
        const dateInputs = document.querySelectorAll("input[type='date']");
        for (const dateInput of dateInputs) {
            if (!validateDateInput(dateInput)) {
                return;
            }
        }

        // Get the selected registry date
        const registryDate = new Date(registryDateInput.value);
        registryDate.setHours(0, 0, 0, 0); // Normalize to midnight for comparison

        // Check if registry date is not today
        if (registryDate.getTime() !== today.getTime()) {
            const confirmation = confirm(
                "The registry date is not today. Are you sure you want to submit this record?"
            );
            if (!confirmation) {
                event.preventDefault(); // Prevent form submission if user clicks "Cancel"
                return;
            }
        }

        // Get the selected services, partial denture, and full denture checkboxes
        const servicesChecked = document.querySelectorAll('.services:checked').length > 0;
        const partialDentureChecked = document.querySelectorAll('.partial_denture:checked').length > 0;
        const fullDentureChecked = document.querySelectorAll('.full_denture:checked').length > 0;

        // Check if at least one checkbox is selected from any of the categories
        if (!servicesChecked && !partialDentureChecked && !fullDentureChecked) {
            alert('Please select at least one option from Services, Partial Denture, or Full Denture.');
            event.preventDefault(); // Prevent form submission
            return;
        }


        const birthday = new Date(birthdayInput.value);
        if (birthday > today) {
            alert("Date of Birth cannot be in the future.");
            event.preventDefault(); // Prevent form submission
            return;
        }

        // Validate Appointment Date - cannot be in the past
        const appointmentDate = new Date(appointmentDateInput.value);
        if (appointmentDate < today) {
            alert("Appointment Date cannot be in the past.");
            event.preventDefault(); // Prevent form submission
            return;
        }

        // Get the selected appointment time range
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;

        // Validate appointment date and times
        const selectedDate = new Date(appointmentDateInput.value);
        selectedDate.setHours(0, 0, 0, 0); // Normalize the time part for comparison

        // Ensure the start time is before the end time
        const [startHour, startMinute] = startTime.split(":").map(Number);
        const [endHour, endMinute] = endTime.split(":").map(Number);
        const startTimeObj = new Date(selectedDate);
        const endTimeObj = new Date(selectedDate);
        startTimeObj.setHours(startHour, startMinute, 0, 0);
        endTimeObj.setHours(endHour, endMinute, 0, 0);

        if (startTimeObj >= endTimeObj) {
            alert("End time must be later than start time.");
            event.preventDefault();
            return;
        }

        // Check if the appointment time is within working hours (1 PM to 5:30 PM)
        const workStart = new Date(selectedDate.setHours(13, 0, 0, 0));
        const workEnd = new Date(selectedDate.setHours(17, 30, 0, 0));

        if (startTimeObj < workStart || endTimeObj > workEnd) {
            alert("Appointment must be within working hours (1:00 PM to 5:30 PM).");
            event.preventDefault();
            return;
        }

        // Check for conflicts with existing appointments (this is just a dummy check)
        // Normally, this data should come from the backend
        const existingAppointments = [
            { date: '2024-12-17', start: '13:00', end: '14:00' },
            { date: '2024-12-17', start: '14:00', end: '15:00' },
            { date: '2024-12-17', start: '16:00', end: '16:30' }
        ];

        // Check if selected time conflicts with existing ones
        const isConflicting = existingAppointments.some(appointment => {
            if (appointment.date === appointmentDateInput.value) {
                const existingStart = new Date(`${appointment.date} ${appointment.start}`);
                const existingEnd = new Date(`${appointment.date} ${appointment.end}`);
                return (startTimeObj < existingEnd && endTimeObj > existingStart);
            }
            return false;
        });

        if (isConflicting) {
            alert("The selected time slot conflicts with an existing appointment.");
            event.preventDefault(); // Prevent form submission
        }
    });
});
