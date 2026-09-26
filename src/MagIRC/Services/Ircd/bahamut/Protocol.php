<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd\bahamut;

// Bahamut protocol file for Magirc
class Protocol
{
    public const IRCD = 'bahamut';

    public const CHAN_MODES = 'cijklmnprstLMOR';
    public const CHAN_MODES_DATA = 'jkl';
    public const USER_MODES = 'abcdefghijkmnorswxyADFIKORX';

    public const OPER_HIDDEN_MODE = '';
    public static $oper_levels = [];
    public const HELPER_MODE = '';
    public const BOT_MODE = '';
    public const SERVICES_PROTECTION_MODE = '';
    public const CHAN_HIDE_MODE = '';
    public const CHAN_SECRET_MODE = 's';
    public const CHAN_PRIVATE_MODE = 'p';

    public const CHAN_EXCEPTION = true;
    public const CHAN_INVITES = true;
    public const LINE_SQ = true;
    public const LINE_G = false;
    public const HOST_CLOAKING = false;
}
