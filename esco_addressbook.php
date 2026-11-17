<?php
/**
 * Created by PhpStorm.
 * User: pierrelejeune
 * Date: 06/07/18
 * Time: 13:12
 */

use EscoAddressbook\LdapBackend;

class esco_addressbook extends rcube_plugin
{
    public function init()
    {
        $this->add_hook('addressbooks_list', array($this, 'getAddressbookList'));
        $this->add_hook('addressbook_get', array($this, 'getAddressbookContent'));

        $config = rcmail::get_instance()->config;
        $sources_config = (array)$config->get('esco_ldap', array());
        $user_data = $_SESSION['user_data'] ?? null;

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
    private function isSourceAvailable(array $source, array $user_data = null) {
        if (is_null($user_data)) {
            return false;
        }

        // Si le carnet n’a pas de champ dynamique, il est toujours disponible
        if (empty($source["dynamic_user_fields"])) {
            return true;
        }

        // On vérifie si l’utilisateur possède au moins un des champs requis
        foreach ($source["dynamic_user_fields"] as $field) {
            if (isset($user_data[strtolower($field)]) && !empty($user_data[strtolower($field)])) {
                return true;
            }
        }

        // Si aucun champ dynamique n’est présent mais qu’on est dans une session normale, on autorise par défaut
        // (utile pour éviter la disparition des carnets dans certains profils)
        return isset($user_data['uid']);
    }

    public function getAddressbookList($p)
    {
        if(!isset(rcmail::get_instance()->user->data['esco_user_inited']) || rcmail::get_instance()->user->data['esco_user_inited'] != "DONE"){
            rcmail::get_instance()->plugins->exec_hook('refresh_user');
        }
        $config = rcmail::get_instance()->config;
        $sources_config = (array)$config->get('esco_ldap', array());

        $user_data = $_SESSION['user_data'] ?? null;
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
        $user_data = $_SESSION['user_data'] ?? null;

        if($this->isSourceAvailable($source,$user_data)){
            $p["instance"] = new LdapBackend(
                $source,
                $config->get('ldap_debug'),
                '',
                'uid'
            );
        }
        return $p;
    }
}
