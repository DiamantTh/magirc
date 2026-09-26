<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd\charybdis;

// Charybdis protocol file for Magirc
class Protocol
{
    public const IRCD = 'charybdis';

    public const CHAN_MODES = 'cfgijklmnprstzFLPQ';
    public const CHAN_MODES_DATA = 'fjkl';
    public const USER_MODES = 'ahgiloswzQRSZ';

    public const OPER_HIDDEN_MODE = '';
    public static $oper_levels = [];
    public const HELPER_MODE = '';
    public const BOT_MODE = '';
    public const SERVICES_PROTECTION_MODE = 'S';
    public const CHAN_HIDE_MODE = '';
    public const CHAN_SECRET_MODE = 's';
    public const CHAN_PRIVATE_MODE = '';

    public const CHAN_EXCEPTION = true;
    public const CHAN_INVITES = true;
    public const LINE_SQ = false;
    public const LINE_G = true;
    public const HOST_CLOAKING = true;
}
