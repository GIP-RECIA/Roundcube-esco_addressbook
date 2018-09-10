<?php
/**
 * Created by PhpStorm.
 * User: pierrelejeune
 * Date: 06/07/18
 * Time: 13:12
 */

class esco_addressbook extends rcube_plugin
{
    public function init()
    {
        $this->add_hook('addressbooks_list', array($this, 'getAddressbookList'));
        $this->add_hook('addressbook_get', array($this, 'getAddressbookContent'));

        $config = rcmail::get_instance()->config;
        $sources_config = (array)$config->get('esco_ldap', array());
        $user_data = $_SESSION['user_data'];

        $autocomplete_sources = (array) $config->get('autocomplete_addressbooks', 'sql');

        foreach($sources_config as $id => $source){
            if($this->isSourceAvailable($source,$user_data)){
                $autocomplete_sources[] = $id;
            }
        }

        $config->set('autocomplete_addressbooks', $autocomplete_sources);
    }

    /**
     * Check wether or not the user can access the given address book
     * @param array $source
     * @param array $user_data
     * @return bool
     */
    private function isSourceAvailable(array $source, array $user_data){
        foreach($source["dynamic_user_fields"] as $field){
            if(!isset($user_data[strtolower($field)])){
                return false;
            }
        }
        return true;
    }

    public function getAddressbookList($p)
    {
        if(!isset(rcmail::get_instance()->user->data['esco_user_inited']) || rcmail::get_instance()->user->data['esco_user_inited'] != "DONE"){
            rcmail::get_instance()->plugins->exec_hook('refresh_user');
        }
        $config = rcmail::get_instance()->config;
        $sources_config = (array)$config->get('esco_ldap', array());

        $user_data = $_SESSION['user_data'];
        foreach ($sources_config as $id => $source) {
            if($this->isSourceAvailable($source,$user_data)){
                $p['sources'][$id] = array(
                    'id' => $id,
                    'name' => $source["name"],
                    'readonly' => $source['writable'] == false,
                    'groups' => false,
                );
            }
        }

        return $p;
    }

    public function getAddressbookContent($p)
    {
        $config = rcmail::get_instance()->config;
        $sources_config = (array)$config->get('esco_ldap', array());


        if (!isset($sources_config[$p["id"]])) {
            return $p;
        }

        $source = $sources_config[$p["id"]];
        $user_data = $_SESSION['user_data'];

        if($this->isSourceAvailable($source,$user_data)){
            $p["instance"] = new esco_addressbook_ldap_backend(
                $source,
                $config->get('ldap_debug'),
                '',
                'uid'
            );
        }
        return $p;
    }
}

class esco_addressbook_ldap_backend extends rcube_ldap
{

    private $str_dyn = '%dynamic';

    function __construct($p, $debug, $mail_domain, $search)
    {
        parent::__construct($p, $debug, $mail_domain);
        $this->prop['search_fields'] = (array)$search;
    }
    protected function extended_search($count = false)
    {
        $this->prop['filter'] = $this->apply_dyn_filter($this->prop['dynamic_filter']);
        if(!empty($this->filter)){
            $this->filter = $this->apply_dyn_filter($this->filter);
        };
        return parent::extended_search($count);
    }

    private function apply_dyn_filter($filter)
    {
        $user_data = $_SESSION['user_data'];
        if (strlen(strstr($filter, $this->str_dyn)) > 0) {
            $dynamic_user_fields = $this->prop['dynamic_user_fields'];
            $dynamic_filter = '';
            $required_respected = true;
            if (!empty($dynamic_user_fields)) {
                $fields = array();
                if (is_array($dynamic_user_fields)) {
                    $fields = $dynamic_user_fields;
                } else {
                    $fields = array($dynamic_user_fields);
                }
                foreach ($fields as $user_attr) {
                    $attr = strtolower($user_attr);
                    if (!empty($user_data[$attr])) {
                        $dynamic_filter .= "(|";
                        foreach ($user_data[$attr] as $val) {
                            if (!empty($val)) {
                                $dynamic_filter .= "(|";
                                foreach ($fields as $user2_attr) {
                                    $attr2 = strtolower($user2_attr);
                                    $dynamic_filter .= "(" . $attr2 . "=" . $val . ")";
                                }
                                $dynamic_filter .= ")";
                            }
                        }
                        $dynamic_filter .= ")";
                    } else if (in_array(strtolower($user_attr), $this->prop['required_fields'])) {
                        $required_respected = false;
                    }
                }
            }
            if ($required_respected || (is_array($user_data) && !array_key_exists('new_user_inited', $user_data))) {
                $new_filter = str_replace($this->str_dyn, $dynamic_filter, $filter);
                $this->dyn_filter = $dynamic_filter;
                return $new_filter;
            }
        }
        return $filter;
    }
}