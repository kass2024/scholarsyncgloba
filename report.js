$(document).ready(function() {

    let table = $("#attendanceTable").DataTable();

    function loadData() {
        $.post("attendance-report-data.php", $("#filterForm").serialize(), function(res) {

            let data = JSON.parse(res);

            // Fill KPIs
            $("#kpiRow").html(`
                <div class="col-md-3">
                    <div class="kpi-card">
                        <h3>${data.kpi.records}</h3>
                        <p>Total Records</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="kpi-card">
                        <h3>${data.kpi.total_minutes} min</h3>
                        <p>Total Work Time</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="kpi-card">
                        <h3>${data.kpi.avg_minutes} min</h3>
                        <p>Average Per Record</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="kpi-card">
                        <h3>${data.kpi.total_salary} RWF</h3>
                        <p>Total Salary</p>
                    </div>
                </div>
            `);

            // Refresh DataTable
            table.clear();
            data.table.forEach(r => {
                table.row.add([
                    r.name,
                    r.date,
                    r.check_in ?? "-",
                    r.check_out ?? "-",
                    r.minutes,
                    r.salary.toLocaleString()
                ]);
            });
            table.draw();

            // CHART
            if (window.chartInstance) window.chartInstance.destroy();

            let ctx = document.getElementById("chartAttendance");
            window.chartInstance = new Chart(ctx, {
                type: "line",
                data: {
                    labels: data.chart.labels,
                    datasets: [{
                        label: "Work Minutes",
                        data: data.chart.values,
                        borderColor: "#007bff",
                        borderWidth: 2,
                        tension: 0.3
                    }]
                }
            });

        });
    }

    // Load initial
    loadData();

    // Filter change
    $("#filterForm").on("submit", function(e) {
        e.preventDefault();
        loadData();
    });

    // Toggle fields
    $("#filter").change(function() {
        $(".filter-date, .filter-week, .filter-month").addClass("d-none");
        if (this.value == "daily") $(".filter-date").removeClass("d-none");
        if (this.value == "weekly") $(".filter-week").removeClass("d-none");
        if (this.value == "monthly") $(".filter-month").removeClass("d-none");
    });

});
