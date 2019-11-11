<?php

namespace App\Member\Entity;

use App\Member\TwitterMemberInterface;

class ExceptionalMember implements TwitterMemberInterface
{
    use MemberTrait;
    use ExceptionalUserInterfaceTrait;
}
