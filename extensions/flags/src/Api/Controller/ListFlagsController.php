<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Flags\Api\Controller;

use Carbon\Carbon;
use Flarum\Api\Controller\AbstractListController;
use Flarum\Flags\Api\Serializer\FlagSerializer;
use Flarum\Flags\Flag;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListFlagsController extends AbstractListController
{
    public ?string $serializer = FlagSerializer::class;

    public array $include = [
        'user',
        'post',
        'post.user',
        'post.discussion'
    ];

    protected function data(ServerRequestInterface $request, Document $document): iterable
    {
        $actor = RequestUtil::getActor($request);
        $include = $this->extractInclude($request);

        $actor->assertRegistered();

        $actor->read_flags_at = Carbon::now();
        $actor->save();

        $flags = Flag::whereVisibleTo($actor)
            ->latest('flags.created_at')
            ->groupBy('post_id')
            ->get();

        if (in_array('post.user', $include)) {
            $include[] = 'post.user.groups';
        }

        $this->loadRelations($flags, $include);

        return $flags;
    }
}
