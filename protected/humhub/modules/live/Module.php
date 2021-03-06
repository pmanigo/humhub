<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2017 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\live;

use Yii;
use humhub\modules\content\models\Content;
use humhub\modules\user\models\User;
use humhub\modules\user\models\Follow;
use humhub\modules\friendship\models\Friendship;
use humhub\modules\space\models\Membership;

/**
 * Live module provides a live channel to the users browser.
 *
 * @since 1.2
 */
class Module extends \humhub\components\Module
{

    /**
     * @inheritdoc
     */
    public $isCoreModule = true;

    /**
     * @var string cache prefix for legitimate content container ids by user
     */
    public static $legitimateCachePrefix = 'live.contentcontainerId.legitmation.';

    /**
     * Returns an array of content container ids which belongs to the given user.
     *
     * There are three separeted lists by visibility level:
     *  - Content::VISIBILITY_PUBLIC [1,2,3,4]   (Public visibility only)
     *  - Content::VISIBILITY_PRIVATE [5,6,7]    (Public and private visibility)
     *  - Content::VISIBILITY_OWNER (10)          (No visibility, direct to the user)
     *
     * @todo Add user to user following
     * @param User $user the User
     * @param boolean $cached use caching
     * @return array multi dimensional array of user content container ids
     */
    public function getLegitimateContentContainerIds(User $user, $cached = true)
    {
        $legitimation = Yii::$app->cache->get(self::$legitimateCachePrefix . $user->id);

        if ($legitimation === false) {
            $legitimation = [
                Content::VISIBILITY_PUBLIC => [],
                Content::VISIBILITY_PRIVATE => [],
                Content::VISIBILITY_OWNER => [],
            ];

            // When no content container record exists (yet)
            // This may happen during the registration process
            if ($user->contentContainerRecord === null) {
                return $legitimation;
            }

            // Add users own content container (user == contentcontainer)
            $legitimation[Content::VISIBILITY_OWNER][] = $user->contentContainerRecord->id;

            // Collect user space membership with private content visibility
            $spaces = Membership::getUserSpaces($user->id);
            foreach ($spaces as $space) {
                $legitimation[Content::VISIBILITY_PRIVATE][] = $space->contentContainerRecord->id;
            }

            // Include friends
            if (Yii::$app->getModule('friendship')->isEnabled) {
                foreach (Friendship::getFriendsQuery($user)->all() as $friend) {
                    $legitimation[Content::VISIBILITY_PRIVATE][] = $friend->contentContainerRecord->id;
                }
            }

            // Collect spaces which the users follows
            foreach (Follow::getFollowedSpacesQuery($user)->all() as $space) {
                $legitimation[Content::VISIBILITY_PUBLIC][] = $space->contentContainerRecord->id;
            }

            Yii::$app->cache->set(self::$legitimateCachePrefix . $user->id, $legitimation);
            Yii::$app->live->driver->onContentContainerLegitimationChanged($user, $legitimation);
        };

        return $legitimation;
    }

    public function refreshLegitimateContentContainerIds(User $user)
    {
        Yii::$app->cache->delete(self::$legitimateCachePrefix . $user->id);
        $this->getLegitimateContentContainerIds($user);
    }

}
