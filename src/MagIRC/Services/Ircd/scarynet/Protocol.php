<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd\scarynet;

// Scarynet protocol file for Magirc
class Protocol
{
    public const IRCD = 'scarynet';

    public const CHAN_MODES = 'ciklmnprstuCDNOT';
    public const CHAN_MODES_DATA = 'kl';
    public const USER_MODES = 'dghikorswxBCHORW';

    public const OPER_HIDDEN_MODE = '';
    public static $oper_levels = [];
    public const HELPER_MODE = 'H';
    public const BOT_MODE = 'B';
    public const SERVICES_PROTECTION_MODE = '';
    public const CHAN_HIDE_MODE = '';
    public const CHAN_SECRET_MODE = 's';
    public const CHAN_PRIVATE_MODE = 'p';

    public const CHAN_EXCEPTION = false;
    public const CHAN_INVITES = false;
    public const LINE_SQ = false;
    public const LINE_G = false;
    public const HOST_CLOAKING = true;
}
