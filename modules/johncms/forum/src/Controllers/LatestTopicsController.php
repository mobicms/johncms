<?php

declare(strict_types=1);

namespace Johncms\Forum\Controllers;

use Johncms\Forum\Resources\UnreadTopicResource;
use Johncms\Forum\Services\ForumTopicService;

class LatestTopicsController extends BaseForumController
{
    public function unread(ForumTopicService $topicRepository): string
    {
        $this->metaTagManager->setAll(__('Unread'));
        $this->navChain->add(__('Unread'));
        $topics = $topicRepository->getUnread()->paginate();
        $resource = UnreadTopicResource::createFromCollection($topics);

        return $this->render->render(
            'forum::new_topics',
            [
                'topics'        => $resource->getItems(),
                'pagination'    => $topics->render(),
                'title'         => __('Unread'),
                'page_title'    => __('Unread'),
                'empty_message' => __('The list is empty'),
                'total'         => $topics->total(),
                'show_period'   => false,
                'mark_as_read'  => '?act=new&amp;do=reset',
            ]
        );
    }
}
