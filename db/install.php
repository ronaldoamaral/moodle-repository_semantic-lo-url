<?php

function xmldb_repository_semantic_lo_url_install() {
    global $CFG;
    $result = true;
    require_once($CFG->dirroot.'/repository/lib.php');
    $semantic_lo_url_plugin = new repository_type('semantic_lo_url', array(), true);
    if (!$id = $semantic_lo_url_plugin->create(true)) {
        $result = false;
    }
    return $result;
}
