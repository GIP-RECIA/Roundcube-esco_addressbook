<?php

namespace EscoAddressbook;

class LdapBackend extends rcube_ldap
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
        $user_data = $_SESSION['user_data'] ?? null;
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
