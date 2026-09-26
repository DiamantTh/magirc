<?php

namespace MagIRC\Statistics\Denora;

use MagIRC\Statistics\Base\ChannelModel;

class Channel extends ChannelModel
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
    private $mode_lf_data;
    private $mode_lj_data;
    private $mode_lk_data;
    private $mode_ll_data;
    private $mode_ul_data;
    private $mode_uf_data;
    private $mode_uj_data;

    public function __construct()
    {
        parent::__construct();

        // Channel modes
        for ($j = 97; $j <= 122; $j++) {
            $mode_l = 'mode_l' . chr($j);
            $mode_u = 'mode_u' . chr($j);
            if (isset($this->$mode_l)) {
                if ($this->$mode_l == "Y") {
                    $this->$mode_l = true;
                    $this->modes .= chr($j);
                } else {
                    $this->$mode_l = false;
                }
            }
            if (isset($this->$mode_u)) {
                if ($this->$mode_u == "Y") {
                    $this->$mode_u = true;
                    $this->modes .= chr($j - 32);
                } else {
                    $this->$mode_u = false;
                }
            }
        }
        // Channel mode data
        if ($this->mode_lf_data) {
            $this->modes_data .= " " . $this->mode_lf_data;
        }
        if ($this->mode_lj_data) {
            $this->modes_data .= " " . $this->mode_lj_data;
        }
        //if ($this->mode_lk_data) $this->modes_data .= " " . $this->mode_lk_data;
        if ($this->mode_ll_data) {
            $this->modes_data .= " " . $this->mode_ll_data;
        }
        if ($this->mode_uf_data) {
            $this->modes_data .= " " . $this->mode_uf_data;
        }
        if ($this->mode_uj_data) {
            $this->modes_data .= " " . $this->mode_uj_data;
        }
        if ($this->mode_ul_data) {
            $this->modes_data .= " " . $this->mode_ul_data;
        }
    }
}
