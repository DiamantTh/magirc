<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd\unreal32;

// Unreal 3.2 protocol file for Magirc
class Protocol
{
    public const IRCD = 'unreal32';

    public const CHAN_MODES = 'cfijklmnprstuzACGKLMNOQRSTV';
    public const CHAN_MODES_DATA = 'fjklL';
    public const USER_MODES = 'adghiopqrstvwxzABCGHNORSTVW';

    public const OPER_HIDDEN_MODE = 'H';
    public static $oper_levels = [
        'N' => 'Network Admin',
        'A' => 'Server Admin',
        'a' => 'Services Admin',
        'C' => 'Co-Admin',
        'o' => 'Global Operator'
    ];
    public const HELPER_MODE = 'h';
    public const BOT_MODE = 'B';
    public const SERVICES_PROTECTION_MODE = 'S';
    public const CHAN_HIDE_MODE = 'p';
    public const CHAN_SECRET_MODE = 's';
    public const CHAN_PRIVATE_MODE = 'p';

    public const CHAN_EXCEPTION = true;
    public const CHAN_INVITES = true;
    public const LINE_SQ = false;
    public const LINE_G = true;
    public const HOST_CLOAKING = true;
}
