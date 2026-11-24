<?php
// ---------------------------------------------------------------------------
// Impostazioni modificabili dal team NIU (pagina Admin → Impostazioni).
// I valori di app/config.php e app/checklist.php restano i default:
// il database salva solo ciò che l'admin cambia.
// ---------------------------------------------------------------------------

const DEFAULT_BLOCKERS = ['doc_telaio', 'body_frame', 'batt_case'];

function setting_get(string $k): ?array
{
    $st = db()->prepare('SELECT v FROM settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : json_decode((string)$v, true);
}

function setting_put(string $k, array $v, int $by): void
{
    db()->prepare("INSERT INTO settings (k, v, updated_at, updated_by) VALUES (?, ?, datetime('now'), ?)
                   ON CONFLICT(k) DO UPDATE SET v = excluded.v, updated_at = excluded.updated_at, updated_by = excluded.updated_by")
        ->execute([$k, json_encode($v), $by]);
}

/** Applica le impostazioni salvate a $CFG e $CHECKLIST. */
function apply_settings(): void
{
    global $CFG, $CHECKLIST, $BLOCKERS, $USED_VALUE;
    $BLOCKERS = DEFAULT_BLOCKERS;
    $USED_VALUE = DEFAULT_USED_VALUE;
    if ($v = setting_get('used_value')) {
        foreach (['curve', 'grade', 'prices'] as $k) if (isset($v[$k])) $USED_VALUE[$k] = array_replace($USED_VALUE[$k], array_map('intval', $v[$k]));
    }
    // Premi: chiave 'incentives' (formato per classe; le vecchie chiavi per categoria sono ignorate)
    if ($v = setting_get('incentives')) {
        if (isset($v['incentive_trade_in'])) $CFG['incentive_trade_in'] = array_replace_recursive($CFG['incentive_trade_in'], array_intersect_key($v['incentive_trade_in'], $CFG['incentive_trade_in']));
        if (isset($v['incentive_trade_up']) && count($v['incentive_trade_up']) === count($CFG['incentive_trade_up'])) $CFG['incentive_trade_up'] = $v['incentive_trade_up'];
        if (isset($v['enabled'])) $CFG['incentives_enabled'] = (bool)$v['enabled'];
    }
    if ($v = setting_get('grade_min')) foreach ($v as $g => $min) if (isset($CFG['grades'][$g])) $CFG['grades'][$g]['min'] = (int)$min;
    if ($v = setting_get('checklist')) {
        $BLOCKERS = [];
        foreach ($CHECKLIST as $sec => $items) {
            foreach ($items as $id => $it) {
                $s = $v[$id] ?? null;
                if ($s === null) { if (in_array($id, DEFAULT_BLOCKERS, true)) $BLOCKERS[] = $id; continue; }
                if (empty($s['on'])) { unset($CHECKLIST[$sec][$id]); continue; }
                $CHECKLIST[$sec][$id][1] = max(0, min(20, (int)$s['w']));
                if (empty($s['cust'])) $CHECKLIST[$sec][$id][3] = null;
                if (!empty($s['block'])) $BLOCKERS[] = $id;
            }
            if (!$CHECKLIST[$sec]) unset($CHECKLIST[$sec]);
        }
    }
}

function blockers(): array { global $BLOCKERS; return $BLOCKERS ?? DEFAULT_BLOCKERS; }

function settings_last_change(): ?array
{
    return db()->query('SELECT s.updated_at, u.name FROM settings s LEFT JOIN users u ON u.id = s.updated_by ORDER BY s.updated_at DESC LIMIT 1')->fetch() ?: null;
}
