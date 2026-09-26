<?php

declare(strict_types=1);

namespace MagIRC\Services\Ircd\ircdrizon;

// ircd-rizon protocol file for Magirc
class Protocol
{
    public const IRCD = 'ircdrizon';

    public const CHAN_MODES = 'ciklmnpstzBMNORSZ';
    public const CHAN_MODES_DATA = 'kl';
    public const USER_MODES = 'abcdfgijklnopqrsuwxyzCDGNRSWX';

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
    public const LINE_G = true;
    public const HOST_CLOAKING = true;
}
