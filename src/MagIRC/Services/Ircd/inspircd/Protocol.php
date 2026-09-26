<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd\inspircd;

// Inspircd 1.2/2.x protocol file for Magirc
class Protocol
{
    public const IRCD = 'inspircd';

    public const CHAN_MODES = 'cfgijklmnprstuzCFGJKLMNOPQRSTV';
    public const CHAN_MODES_DATA = 'fjklFLJ';
    public const USER_MODES = 'cdghinorswxBGHIQRSW';

    public const OPER_HIDDEN_MODE = 'H';
    public static $oper_levels = [];
    public const HELPER_MODE = 'h';
    public const BOT_MODE = 'B';
    public const SERVICES_PROTECTION_MODE = '';
    public const CHAN_HIDE_MODE = 'I';
    public const CHAN_SECRET_MODE = 's';
    public const CHAN_PRIVATE_MODE = 'p';

    public const CHAN_EXCEPTION = true;
    public const CHAN_INVITES = true;
    public const LINE_SQ = false;
    public const LINE_G = true;
    public const HOST_CLOAKING = true;
}
