<?php

namespace Oro\Bundle\NavigationBundle\Event;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\Response;
use Oro\Bundle\NavigationBundle\Entity\Builder\ItemFactory;

class ResponseHistoryListener
{
    const NAVIGATION_HISTORY_ITEM_TYPE = 'history';
    protected $_navItemFactory = null,
              $_user = null,
              $_em = null;

    public function __construct(ItemFactory $navigationItemFactory, $securityContext, $entityManager)
    {
        $this->_navItemFactory = $navigationItemFactory;
        $this->_user = $securityContext->getToken()->getUser();
        $this->_em = $entityManager;
    }

    public function onResponse(FilterResponseEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = $request->get('_route');

        // do not process requests other than in html format with 200 OK status using GET method and not _internal and _wdt
        if ($response->getStatusCode() != 200 || $request->getRequestFormat() != 'html' || $request->getMethod() != 'GET' ||  $route[0] == '_') {
            return false;
        }

        $title = 'Default Title';
        if (preg_match('#<title>([^<]+)</title>#msi', $response->getContent(), $match)) {
            $title = $match[1];
        }

        $postArray = array(
            'title'    => $title,
            'url'      => $request->getRequestUri(),
            'user'  => $this->_user,
            'type'     => self::NAVIGATION_HISTORY_ITEM_TYPE,
            'position' => 0,
        );

        /** @var $entity \Oro\Bundle\NavigationBundle\Entity\NavigationItemInterface */
        $entity = $this->_navItemFactory->createItem(self::NAVIGATION_HISTORY_ITEM_TYPE, $postArray);
        //$a = $this->_navItemFactory->findItem('history');
        $manager = $this->_em;
        $manager->persist($entity);
        $manager->flush();
    }
}