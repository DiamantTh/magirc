<?php

namespace MagIRC\Statistics\Denora;

use MagIRC\Statistics\Base\UserModel;
use MagIRC\Services\Ircd\ProtocolRegistry;

class User extends UserModel
{
    private $mode_la;
    private $mode_lb;
    private $mode_lc;
    private $mode_ld;
    private $mode_le;
    private $mode_lf;
    private $mode_lg;
    private $mode_lh;
    private $mode_li;
    private $mode_lj;
    private $mode_lk;
    private $mode_ll;
    private $mode_lm;
    private $mode_ln;
    private $mode_lo;
    private $mode_lp;
    private $mode_lq;
    private $mode_lr;
    private $mode_ls;
    private $mode_lt;
    private $mode_lu;
    private $mode_lv;
    private $mode_lw;
    private $mode_lx;
    private $mode_ly;
    private $mode_lz;
    private $mode_ua;
    private $mode_ub;
    private $mode_uc;
    private $mode_ud;
    private $mode_ue;
    private $mode_uf;
    private $mode_ug;
    private $mode_uh;
    private $mode_ui;
    private $mode_uj;
    private $mode_uk;
    private $mode_ul;
    private $mode_um;
    private $mode_un;
    private $mode_uo;
    private $mode_up;
    private $mode_uq;
    private $mode_ur;
    private $mode_us;
    private $mode_ut;
    private $mode_uu;
    private $mode_uv;
    private $mode_uw;
    private $mode_ux;
    private $mode_uy;
    private $mode_uz;
    private $cmode_lq;
    private $cmode_la;
    private $cmode_lo;
    private $cmode_lh;
    private $cmode_lv;

    public function __construct()
    {
        parent::__construct();

        // User modes
        for ($j = 97; $j <= 122; $j++) {
            $mode_l = 'mode_l' . chr($j);
            $mode_u = 'mode_u' . chr($j);
            if (isset($this->$mode_l)) {
                if ($this->$mode_l == "Y") {
                    $this->$mode_l = true;
                    $this->umodes .= chr($j);
                } else {
                    $this->$mode_l = false;
                }
            }
            if (isset($this->$mode_u)) {
                if ($this->$mode_u == "Y") {
                    $this->$mode_u = true;
                    $this->umodes .= chr($j - 32);
                } else {
                    $this->$mode_u = false;
                }
            }
        }

        // Channel modes
        $cmodes = null;
        if ($this->cmode_lq == 'Y') {
            $cmodes .= "q";
        }
        if ($this->cmode_la == 'Y') {
            $cmodes .= "a";
        }
        if ($this->cmode_lo == 'Y') {
            $cmodes .= "o";
        }
        if ($this->cmode_lh == 'Y') {
            $cmodes .= "h";
        }
        if ($this->cmode_lv == 'Y') {
            $cmodes .= "v";
        }
        $this->cmodes = $cmodes;

        // Oper mode
        if (!$this->hasMode(ProtocolRegistry::constant('oper_hidden_mode'))) {
            $levels = ProtocolRegistry::staticProperty('oper_levels');
            if (!empty($levels)) {
                foreach ($levels as $mode => $level) {
                    $mode = \MagIRC\Services\Denora\DenoraService::getSqlMode($mode);
                    if ($this->$mode) {
                        $this->operator_level = $level;
                        break;
                    }
                }
            } elseif ($this->mode_lo) {
                $this->operator_level = "Operator";
            }
            if ($this->operator_level) {
                $this->operator = true;
            }
        }
    }
}
