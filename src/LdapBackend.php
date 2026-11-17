<?php

namespace EscoAddressbook;

class LdapBackend extends rcube_ldap
{
    private $str_dyn = '%dynamic';

    function __construct($p, $debug, $mail_domain, $search)
    {
        parent::__construct($p, $debug, $mail_domain);
        $this->prop['search_fields'] = (array) $search;
    }

    protected function extended_search($count = false)
    {
        $this->prop['filter'] = $this->apply_dyn_filter($this->prop['dynamic_filter']);
        if (!empty($this->filter)) {
            $this->filter = $this->apply_dyn_filter($this->filter);
        };
        return parent::extended_search($count);
    }

    /**
     * Replace the %dynamic placeholder in an LDAP filter with a clause built
     * from the current user's dynamic attributes (e.g. class/group membership).
     * If a required field is missing from the user's data, the filter is
     * returned unchanged (no dynamic clause applied).
     */
    private function apply_dyn_filter($filter)
    {
        $user_data = $_SESSION['user_data'] ?? null;
        if (!str_contains($filter, $this->str_dyn)) {
            return $filter;
        }

        $fields = (array) ($this->prop['dynamic_user_fields'] ?? []);
        [$dynamic_filter, $required_respected] = $this->buildDynamicFilter($fields, $user_data);

        if ($required_respected || (is_array($user_data) && !array_key_exists('new_user_inited', $user_data))) {
            $new_filter = str_replace($this->str_dyn, $dynamic_filter, $filter);
            $this->dyn_filter = $dynamic_filter;
            return $new_filter;
        }

        return $filter;
    }

    /**
     * Build the dynamic LDAP clause for a list of configured fields, and
     * report whether all required fields were present in the user's data.
     *
     * @return array{0: string, 1: bool} [dynamic_filter, required_respected]
     */
    private function buildDynamicFilter(array $fields, ?array $user_data): array
    {
        $dynamic_filter = '';
        $required_respected = true;

        foreach ($fields as $user_attr) {
            $attr = strtolower($user_attr);

            if (empty($user_data[$attr])) {
                if (in_array($attr, array_map('strtolower', $this->prop['required_fields']))) {
                    $required_respected = false;
                }
                continue;
            }

            $dynamic_filter .= '(|' . $this->matchAnyFieldForValues($fields, $user_data[$attr]) . ')';
        }

        return [$dynamic_filter, $required_respected];
    }

    /**
     * Build an LDAP OR-clause matching any of the given fields against any
     * of the given values (e.g. "is the user in class A OR class B, checked
     * against both the class and group attributes").
     */
    private function matchAnyFieldForValues(array $fields, array $values): string
    {
        $clauses = '';
        foreach ($values as $val) {
            if (empty($val)) {
                continue;
            }
            $perField = '';
            foreach ($fields as $field) {
                $perField .= '(' . strtolower($field) . '=' . $val . ')';
            }
            $clauses .= '(|' . $perField . ')';
        }
        return $clauses;
    }

}
