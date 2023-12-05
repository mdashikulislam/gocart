<?php
function get_settings($code)
{
    $ci = &get_instance();
    $ci->db->where('code', $code);
    $result	= $ci->db->get('settings');

    $return	= array();
    foreach($result->result() as $results)
    {
        $return[$results->setting_key]	= $results->setting;
    }
    return $return;
}