<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd\ircu;

// IRCu protocol file for Magirc
class Protocol
{
    public const IRCD = 'ircu';

    public const CHAN_MODES = 'iklmnprst';
    public const CHAN_MODES_DATA = 'kl';
    public const USER_MODES = 'dgikorsxw';

    public const OPER_HIDDEN_MODE = '';
    public static $oper_levels = [];
    public const HELPER_MODE = '';
    public const BOT_MODE = '';
    public const SERVICES_PROTECTION_MODE = 'k';
    public const CHAN_HIDE_MODE = '';
    public const CHAN_SECRET_MODE = 's';
    public const CHAN_PRIVATE_MODE = 'p';

    public const CHAN_EXCEPTION = false;
    public const CHAN_INVITES = false;
    public const LINE_SQ = false;
    public const LINE_G = false;
    public const HOST_CLOAKING = false;
}
