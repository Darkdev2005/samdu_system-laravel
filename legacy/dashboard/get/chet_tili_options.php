<?php
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    $fanOptionsBySemestr = [];
    $fanResult = $db->query("
        SELECT f.id, f.fan_name, f.fan_code, f.semestr_id, f.kafedra_id,
               k.name AS kafedra_name,
               s.semestr AS semestr_num,
               y.name AS yonalish_name,
               y.kirish_yili AS yonalish_yili
        FROM fanlar f
        LEFT JOIN kafedralar k ON k.id = f.kafedra_id
        LEFT JOIN semestrlar s ON s.id = f.semestr_id
        LEFT JOIN yonalishlar y ON y.id = s.yonalish_id
        WHERE f.tanlov_fan = 3 AND f.kafedra_id > 0
        ORDER BY f.fan_name, y.name, y.kirish_yili, f.id DESC
    ");

    if ($fanResult) {
        $seenFanIds = [];
        while ($row = mysqli_fetch_assoc($fanResult)) {
            $semestrId = (int) ($row['semestr_id'] ?? 0);
            if ($semestrId <= 0) {
                continue;
            }
            $fanId = (int) ($row['id'] ?? 0);
            if ($fanId <= 0 || isset($seenFanIds[$fanId])) {
                continue;
            }
            $seenFanIds[$fanId] = true;

            $label = trim($row['fan_name']);
            if (!empty($row['kafedra_name'])) {
                $label .= ' (' . $row['kafedra_name'] . ')';
            } else {
                $label .= ' (Kafedra belgilanmagan)';
            }

            $yonalishLabel = trim($row['yonalish_name'] ?? '');
            $yonalishYili = trim($row['yonalish_yili'] ?? '');
            if ($yonalishLabel !== '') {
                $label .= ' — ' . $yonalishLabel;
                if ($yonalishYili !== '') {
                    $label .= ' - ' . $yonalishYili;
                }
            }

            if (!isset($fanOptionsBySemestr[$semestrId])) {
                $fanOptionsBySemestr[$semestrId] = '';
            }
            $fanOptionsBySemestr[$semestrId] .= '<option value="' . $fanId . '">' . htmlspecialchars($label) . '</option>';
        }
    }

    echo json_encode([
        'success' => true,
        'fanOptionsBySemestr' => $fanOptionsBySemestr
    ]);
?>
