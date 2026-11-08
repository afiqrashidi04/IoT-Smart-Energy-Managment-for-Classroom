<?php
// Set zon masa Malaysia di peringkat teratas skrip
date_default_timezone_set('Asia/Kuala_Lumpur');

include "header.template.php";

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "smart_classroom";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 1. Fetch record dari database
$sql = "SELECT * FROM room_log WHERE room_id = 1 ORDER BY log_id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);

$count = 0;
$lightStatus = 0;
$fanStatus = 0;
$lightStatusText = "OFF";
$fanStatusText = "OFF";
$motionStatusText = "CLEAR";
$timestamp = "-";
$ldrStatusText = "LIGHT";

if ($result) {
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $count = (int)$row['user_count'];
        $lightStatus = (int)$row['light_status'];
        $fanStatus = (int)$row['fan_status'];
        
        if (isset($row['timestamp'])) {
            $timestamp = $row['timestamp'];
        } else {
            $timestamp = "-";
        }

        if ($lightStatus == 1) {
            $lightStatusText = "ON";
        } else {
            $lightStatusText = "OFF";
        }

        if ($fanStatus == 1) {
            $fanStatusText = "ON";
        } else {
            $fanStatusText = "OFF";
        }

        if ($row['ldr_status'] == 0) {
            $ldrStatusText = "LIGHT";
        } else {
            $ldrStatusText = "DARK";
        }

        if ($row['motion_status'] == 1) {
            $motionStatusText = "DETECTED";
        } else {
            $motionStatusText = "CLEAR";
        }
    }
}

// 2. Semak Mod Kawalan
$controlMode = 0;
$sql_ctrl = "SELECT control_mode FROM device_control WHERE room_id = 1 LIMIT 1";
$res_ctrl = mysqli_query($conn, $sql_ctrl);

if ($res_ctrl) {
    if (mysqli_num_rows($res_ctrl) > 0) {
        $row_c = mysqli_fetch_assoc($res_ctrl);
        $controlMode = (int)$row_c['control_mode'];
    }
}

// 3. LOGIK PEMERIKSAAN PERANTI TERBIAR (> 5 SAAT)
$showAlert = false;

if ($count == 0) {
    if ($lightStatus == 1 || $fanStatus == 1) {
        if ($timestamp != "-") {
            $logTime = strtotime($timestamp);
            $currentTime = time();
            
            // Gunakan abs() untuk elak nilai negatif akibat perbezaan jam server
            $timeDifference = abs($currentTime - $logTime);

            // Jika tiada orang & lampu/kipas ON melebihi 5 saat
            if ($timeDifference >= 5) {
                $showAlert = true;
            }
        }
    }
}

// 4. Fetch data Heatmap (Maksimum Minggu Semasa)
$heatmapDataArray = array();
$sql_heatmap = "SELECT 
                    (DAYOFWEEK(timestamp) - 1) AS day_index, 
                    HOUR(timestamp) AS hour_index, 
                    MAX(user_count) AS max_users 
                FROM room_log 
                WHERE room_id = 1 
                  AND YEARWEEK(timestamp, 1) = YEARWEEK(CURDATE(), 1)
                GROUP BY day_index, hour_index";

$res_heatmap = mysqli_query($conn, $sql_heatmap);

if ($res_heatmap) {
    if (mysqli_num_rows($res_heatmap) > 0) {
        while ($h_row = mysqli_fetch_assoc($res_heatmap)) {
            $h_day  = (int)$h_row['day_index'];
            $h_hour = (int)$h_row['hour_index'];
            $h_max  = (int)$h_row['max_users'];
            $heatmapDataArray[] = array($h_hour, $h_day, $h_max);
        }
    }
}

$jsonHeatmapData = json_encode($heatmapDataArray);

mysqli_close($conn);

$autoBtnClass = "btn-outline-primary";
if ($controlMode == 0) {
    $autoBtnClass = "btn-primary";
}

$onBtnClass = "btn-outline-success";
if ($controlMode == 1) {
    $onBtnClass = "btn-success";
}

$offBtnClass = "btn-outline-danger";
if ($controlMode == 2) {
    $offBtnClass = "btn-danger";
}
?>

<!DOCTYPE html>
<html>
<head>
    <!-- Refresh halaman setiap 2 saat untuk mengemas kini pengiraan masa secara langsung -->
    <meta http-equiv="refresh" content="2">
    <title>Room Status</title>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/heatmap.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
</head>
<body>

    <div class="container-fluid">
        <h2 class="h3 mb-4 text-gray-800">BILIK KULIAH</h2>

        <!-- PAPARAN BANNER AMARAN (BILA TERBIAR > 5 SAAT) -->
        <?php if ($showAlert) { ?>
            <div class="alert alert-danger alert-dismissible fade show shadow mb-4" role="alert">
                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> AMARAN: PERANTI TERBIAR!</h4>
                <p class="mb-0">Tiada pengguna dikesan di dalam kelas tetapi lampu/kipas masih menyala melebihi 5 saat. Sila tekan butang <strong>OFF</strong> di bawah untuk mematikan perkakas.</p>
            </div>
        <?php } ?>

        <!-- Panel Kawalan Manual Override -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header font-weight-bold text-primary">LAMP & FAN CONTROL</div>
                    <div class="card-body d-flex gap-2">
                        <form action="update_control.php" method="POST" class="mr-2" style="display:inline;">
                            <input type="hidden" name="mode" value="0">
                            <button type="submit" class="btn <?php echo $autoBtnClass; ?>">AUTO</button>
                        </form>
                        <form action="update_control.php" method="POST" class="mr-2" style="display:inline;">
                            <input type="hidden" name="mode" value="1">
                            <button type="submit" class="btn <?php echo $onBtnClass; ?>">ON</button>
                        </form>
                        <form action="update_control.php" method="POST" style="display:inline;">
                            <input type="hidden" name="mode" value="2">
                            <button type="submit" class="btn <?php echo $offBtnClass; ?>">OFF</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">

            <!-- Total Users Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $count; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LDR Status Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">LDR Status</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $ldrStatusText; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-lightbulb fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Light Status Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Lamp Status</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $lightStatusText; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-lightbulb fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fan Status Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Fan Status</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $fanStatusText; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-fan fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Motion Detector Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Motion Detector</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $motionStatusText; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-walking fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Last Updated Card -->
            <div class="col-xl-12 col-md-12 mb-4">
                <div class="card border-left-secondary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Last Updated</div>
                                <div class="h6 mb-0 font-weight-bold text-gray-800"><?php echo $timestamp; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HEATMAP CHART CARD -->
            <div class="col-xl-12 col-md-12 mb-4">
                <div class="card shadow">
                    <div class="card-body">
                        <div id="container" style="height: 400px;"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- SCRIPT HIGHCHARTS HEATMAP -->
    <script>
    Highcharts.Templating.helpers.substr = function(s, from, length) {
        return String(s).substr(from, length);
    };

    var dbHeatmapData = <?php echo $jsonHeatmapData; ?>;

    Highcharts.chart('container', {
        chart: {
            type: 'heatmap',
            plotBorderColor: '#0443E1',
            plotBorderWidth: 2,
            plotBorderRadius: 5,
            marginBottom: 25
        },

        title: {
            text: 'Analysis Classroom Occupancy',
            align: 'left'
        },

        xAxis: {
            categories: [
                '00:00', '01:00', '02:00', '03:00', '04:00', '05:00',
                '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
                '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
                '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'
            ],
            opposite: true,
            labels: {
                autoRotation: [0, -90],
                style: { fontSize: '0.65em' }
            },
            lineWidth: 1,
            lineColor: '#0443E1'
        },

        yAxis: {
            categories: ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'],
            title: null,
            reversed: true,
            labels: {
                rotation: 0,
                style: { fontSize: '0.65em' }
            }
        },

        accessibility: {
            point: {
                descriptionFormat: 'Pada hari {series.yAxis.categories.(y)} jam {series.xAxis.categories.(x)}, bilangan maksimum ialah {value} pengguna.'
            }
        },

        colorAxis: {
            min: 0,
            max: 30,
            startOnTick: false,
            endOnTick: false,
            tickPixelInterval: 50,
            minColor: '#0443E111',
            maxColor: '#0443E1',
            tickColor: '#ffffff',
            tickLength: 0.1,
            tickWidth: 6,
            gridLineWidth: 0,
            marker: {
                symbol: 'circle',
                color: 'transparent',
                lineColor: '#fff',
                lineWidth: 3
            }
        },

        legend: {
            align: 'right',
            layout: 'vertical',
            symbolRadius: 6,
            margin: 0,
            verticalAlign: 'bottom',
            symbolHeight: 270
        },

        tooltip: {
            useHTML: true,
            format: `<div style="border-left: 3px solid {point.color}; padding-left: 8px;">
                    Hari: <b>{series.yAxis.categories.(point.y)}</b><br>
                    Masa: <b>{series.xAxis.categories.(point.x)}</b><br>
                    Maksimum Pelajar: <b>{point.value} orang</b></div>`,
            padding: 12
        },

        series: [{
            name: 'Kepadatan Maksimum',
            borderColor: '#0443E1',
            borderWidth: 1,
            data: dbHeatmapData,
            dataLabels: {
                enabled: true,
                style: { fontSize: '0.85em' }
            }
        }],

        responsive: {
            rules: [{
                condition: { maxWidth: 500 },
                chartOptions: {
                    yAxis: {
                        labels: {
                            rotation: 0,
                            format: '{substr value 0 3}'
                        }
                    }
                }
            }]
        }
    });
    </script>

</body>
</html>

<?php
include "footer.template.php";
?>