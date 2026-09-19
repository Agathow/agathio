function calculateDuration(startDate, endDate = new Date()) {
    const start = new Date(startDate);
    const end = new Date(endDate);

    let years = end.getFullYear() - start.getFullYear();
    let months = end.getMonth() - start.getMonth();

    if (end.getDate() < start.getDate()) {
        months--;
    }

    if (months < 0) {
        years--;
        months += 12;
    }

    const duration = [];

    if (years > 0) {
        duration.push(
            `${years} ${years === 1 ? "yr" : "yrs"}`
        );
    }

    if (months > 0) {
        duration.push(`${months} mos`);
    }

    if (duration.length === 0) {
        return "Less than 1 mo";
    }

    return duration.join(" ");
}


/* ==========================================
   UPDATE COMPANY + ROLE DURATIONS
========================================== */

function updateDurations() {

    // Company total duration
    document.querySelectorAll(".duration").forEach(element => {

        const startDate = element.dataset.start;
        const endDate = element.dataset.end;

        if (!startDate) return;

        element.textContent = calculateDuration(
            startDate,
            endDate || new Date()
        );

    });


    // Individual role duration
    document.querySelectorAll(".role-duration").forEach(element => {

        const startDate = element.dataset.start;
        const endDate = element.dataset.end;

        if (!startDate) return;

        element.textContent =
            ` · ${calculateDuration(
                startDate,
                endDate || new Date()
            )}`;

    });
}


/* ==========================================
   FILTER SYSTEM
========================================== */

function setupFilters() {

    const filterButtons =
        document.querySelectorAll(".filter-btn");

    const timelineItems =
        document.querySelectorAll(".timeline-item");


    filterButtons.forEach(button => {

        button.addEventListener("click", () => {

            const selectedCategory =
                button.dataset.filter;


            // Update active button
            filterButtons.forEach(btn => {
                btn.classList.remove("active");
            });

            button.classList.add("active");


            // Filter timeline
            timelineItems.forEach(item => {

                if (
                    item.dataset.category ===
                    selectedCategory
                ) {

                    item.style.display = "flex";

                    item.style.animation = "none";

                    void item.offsetWidth;

                    item.style.animation =
                        "fadeIn 0.6s forwards";

                } else {

                    item.style.display = "none";

                }

            });

        });

    });

}


/* ==========================================
   INITIALIZE
========================================== */

document.addEventListener(
    "DOMContentLoaded",
    () => {

        updateDurations();
        setupFilters();

    }
);
