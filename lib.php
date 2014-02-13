<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin is used to URL reference in Moodle
 *
 * @since 2.0
 * @package    repository_semantic_lo_url
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * A repository plugin to allow user URL
 *
 * @since 2.0
 * @package    repository_semantic_lo_url
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class repository_semantic_lo_url extends repository {
    private $mimetypes = array();


    /**
     * Semantic LO URL plugin constructor
     * @param int $repositoryid
     * @param object $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);
        $this->semantic_lo_service = $this->get_option('semantic_lo_service');
        $this->semantic_lo_repository = $this->get_option('semantic_lo_repository');
        $this->url = optional_param('url', '', PARAM_RAW);
        $this->title = optional_param('title', '', PARAM_RAW);
    }
     /**
     * Return names of the options to display in the repository instance form
     *
     * @return array of option names
     */
    public static function get_instance_option_names() {
        return array('semantic_lo_service', 'semantic_lo_repository');
    }
    public static function instance_config_form($mform) {
        $strrequired = get_string('required');
        $mform->addElement('text', 'semantic_lo_service', get_string('semantic_lo_service', 'repository_semantic_lo_upload'));
        $mform->addRule('semantic_lo_service', $strrequired, 'required', null, 'client');
        $mform->setType('semantic_lo_service', PARAM_URL);
        $mform->setDefault('semantic_lo_service', 'http://localhost:5000/');
        $mform->addElement('text', 'semantic_lo_repository', get_string('semantic_lo_repository', 'repository_semantic_lo_upload'));
        $mform->addRule('semantic_lo_repository', $strrequired, 'required', null, 'client');
        $mform->setType('semantic_lo_repository', PARAM_URL);
        $mform->setDefault('semantic_lo_repository', 'http://localhost:8080/semanticlo/resource/');
    }

    public function check_login() {
        if (!empty($this->url)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Print a upload form
     * @return array
     */
    public function print_login($ajax = true) {
        global $PAGE;
        
        //https://moodle.org/mod/forum/discuss.php?d=169124
        //$PAGE->requires->js('/local/tokenizer.min.js', true);
        
        //$popup_btn = new stdClass();
        //$popup_btn->type = 'popup';
        //$popup_btn->url = "http://www.google.com";

        
        $ret = array();
        
        $url = new stdClass();
        $url->type = 'text';
        $url->id   = 'url';
        $url->name = 'url';
        $url->label = get_string('url', 'repository_semantic_lo_url').': ';
        
        $title = new stdClass();
        $title ->type = 'text';
        $title->id   = 'title';
        $title->name = 'title';
        $title->label = get_string('title', 'repository_semantic_lo_url').': ';
        

        $ret['login'] = array($url, $title);
        $ret['login_btn_label'] = get_string('URL');
        //$ret['login_btn_action'] = 'process_url';
        $ret['allowcaching'] = false; // indicates that login form can be cached in filepicker.js
        
        return $ret;
    }
    
     /**
     * Return a upload form
     * @return array
     */
    public function get_listing($path = '', $page = '') {
        global $CFG, $OUTPUT;
        
        $ret = array();
        //$ret['help']  = "http://example.com";
        //$ret['manage']  = 'http://www.example.com';        
        $ret['nologin']  = true;
        $ret['nosearch'] = true;
        $ret['norefresh'] = true;
        $ret['list'] = $this->process_url($this->url, $this->title);
        $ret['allowcaching'] = false; // indicates that result of get_listing() can be cached in filepicker.js   
                
        //////////
        
        $callbackurl = new moodle_url('/repository/semantic_lo_upload/callback.php', array('repo_id'=>$this->id));

        $url = $this->get_option('semantic_lo_service')
                . 'addmetadata?uri='.$this->url
                . '&returnurl='.urlencode($callbackurl);        
        
        $ret['object'] = array();
        $ret['object']['type'] = 'text/html';
        $ret['object']['src'] = $url;     
        
        ///////////
        
        return $ret;
    }
   
    public function process_url($url, $title) {
        $list = array(); 
        
        
        $curl = new curl;
        $curl->setopt(array('CURLOPT_FOLLOWLOCATION' => true, 'CURLOPT_MAXREDIRS' => 3));
        $msg = $curl->head($url);
        $info = $curl->get_info();
        if ($info['http_code'] != 200) {
            $list['error'] = $msg;
        }
        else {     
            $description = '';
            if (empty($description)) {
                $description = $title;
            }
        
            // Funcao para Adicionar ao Repositorio Semantico
       
            $data = array('title'=>$title, 'identifier'=>$url);         
            $this->_add_lo_repository($data);
        
            //
            $list[] = array(
                    'shorttitle'=>$title,
                    'thumbnail_title'=>$description,
                    'title'=>$title,
                    'thumbnail'=>'',
                    'thumbnail_width'=>0,
                    'thumbnail_height'=>0,
                    'size'=>'',
                    'date'=>'',
                    'source'=>$url,
                );
        }
        return $list;
       
    }

    /**
      Add file or URL in Semantic LO Repository
    */
    private function _add_lo_repository($data) {
            
        $this->add_url = $this->semantic_lo_service . 'add';
        
        $c = new curl();
       
        $postdata = format_postdata_for_curlcall($data);
        
        $resp = $c->post($this->add_url, $postdata);
                
        $results = json_decode($resp, true);
        
        return $results;
    }

    /**
     * supported return types
     * @return int
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }
    
    public function supported_filetypes() {
        return array('*');
    }

    /**
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }
}
