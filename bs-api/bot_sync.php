<?php
/**
 * Supabase sync helpers - no bot API, no MySQL
 */
require_once __DIR__.'/../config.php';

function get_live_auctions(): array {
    try {
        $rows = sb('bs_auctions')->eq('status', 'active')->order('ends_at', true)->get();
        foreach ($rows as &$r) {
            $r['bids']      = is_array($r['bids_json']) ? $r['bids_json'] : (json_decode($r['bids_json']??'{}', true) ?: []);
            $r['usernames'] = is_array($r['usernames_json']) ? $r['usernames_json'] : (json_decode($r['usernames_json']??'{}', true) ?: []);
            $r['expires_at']= $r['ends_at'] ? strtotime($r['ends_at']) : 0;
        }
        return $rows;
    } catch(Exception $e) { return []; }
}
