<?php

class User extends UserBase {

    public function __construct() {
        parent::__construct();

        // Anope does not keep offline users
        $this->online = true;

        // Oper mode
        if (!$this->hasMode(Protocol::oper_hidden_mode)) {
            $levels = Protocol::$oper_levels;
            if (!empty($levels)) {
                foreach ($levels as $mode => $level) {
                    if (str_contains($this->umodes, $mode)) {
                        $this->operator_level = $level;
                        break;
                    }
                }
            } elseif (str_contains($this->umodes, 'o')) {
                $this->operator_level = "Operator";
            }
            if ($this->operator_level) $this->operator = true;
        }
    }
}
