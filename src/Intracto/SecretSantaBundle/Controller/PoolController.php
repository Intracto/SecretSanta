<?php

namespace Intracto\SecretSantaBundle\Controller;

use Intracto\SecretSantaBundle\Event\PoolEvent;
use Intracto\SecretSantaBundle\Event\PoolEvents;
use Intracto\SecretSantaBundle\Form\ForgotLinkType;
use Intracto\SecretSantaBundle\Form\PoolExcludeEntryType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityRepository;
use JMS\DiExtraBundle\Annotation as DI;
use Intracto\SecretSantaBundle\Entity\Pool;
use Intracto\SecretSantaBundle\Form\PoolType;

class PoolController extends Controller
{
    /**
     * @DI\Inject("%admin_email%")
     */
    public $adminEmail;

    /**
     * @DI\Inject("pool_repository")
     *
     * @var EntityRepository
     */
    public $poolRepository;

    /**
     * @DI\Inject("entry_repository")
     *
     * @var EntityRepository
     */
    public $entryRepository;

    /**
     * @DI\Inject("event_dispatcher")
     *
     * @var EventDispatcherInterface
     */
    public $eventDispatcher;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @Route("/", name="pool_create")
     * @Template()
     */
    public function createAction(Request $request)
    {
        $pool = new Pool();

        return $this->handlePoolCreation($request, $pool);
    }

    /**
     * @Route("/reuse/{listUrl}", name="pool_reuse")
     * @Template("IntractoSecretSantaBundle:Pool:create.html.twig")
     */
    public function reuseAction(Request $request, $listUrl)
    {
        $this->getPool($listUrl);
        $pool = $this->pool->createNewForReuse();

        return $this->handlePoolCreation($request, $pool);
    }

    /**
     * @Route("/exclude/{listUrl}", name="pool_exclude")
     * @Template()
     */
    public function excludeAction(Request $request, $listUrl)
    {
        $this->getPool($listUrl);

        if ($this->pool->getCreated()) {
            return $this->redirect($this->generateUrl('pool_created', array('listUrl' => $this->pool->getListurl())));
        }

        $form = $this->createForm(new PoolExcludeEntryType(), $this->pool);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();

                $this->pool->setCreated(true);
                $em->persist($this->pool);

                /** @var \Intracto\SecretSantaBundle\Entity\EntryService $entryService */
                $entryService = $this->get('intracto_secret_santa.entry_service');

                $entryService->shuffleEntries($this->pool);

                $em->flush();

                $this->eventDispatcher->dispatch(
                    PoolEvents::NEW_POOL_CREATED,
                    new PoolEvent($this->pool)
                );

                return $this->redirect($this->generateUrl('pool_created', array('listUrl' => $this->pool->getListurl())));
            }
        }

        return array(
            'form' => $form->createView(),
            'pool' => $this->pool,
        );
    }

    /**
     * @Route("/created/{listUrl}", name="pool_created")
     * @Template()
     */
    public function createdAction($listUrl)
    {
        $this->getPool($listUrl);
        if (!$this->pool->getCreated()) {
            return $this->redirect($this->generateUrl('pool_exclude', array('listUrl' => $this->pool->getListurl())));
        }

        return array('pool' => $this->pool);
    }

    /**
     * @Route("/manage/{listUrl}", name="pool_manage")
     * @Template()
     */
    public function manageAction($listUrl)
    {
        $this->getPool($listUrl);
        if (!$this->pool->getCreated()) {
            return $this->redirect($this->generateUrl('pool_exclude', array('listUrl' => $this->pool->getListurl())));
        }

        if ($this->pool->getSentdate() === null) {
            $translator = $this->get('translator');
            $this->get('session')->getFlashBag()->add(
                'success',
                $translator->trans('flashes.manage.email_validated')
            );
            /** @var \Intracto\SecretSantaBundle\Entity\EntryService $entryService */
            $entryService = $this->get('intracto_secret_santa.entry_service');

            $entryService->sendSecretSantaMailsForPool($this->pool);
        }

        return array(
            'pool' => $this->pool,
            'delete_pool_csrf_token' => $this->get('security.csrf.token_manager')->getToken('delete_pool'),
            'expose_pool_csrf_token' => $this->get('security.csrf.token_manager')->getToken('expose_pool'),
        );
    }

    /**
     * @Route("/delete/{listUrl}", name="pool_delete")
     * @Template()
     */
    public function deleteAction(Request $request, $listUrl)
    {
        $correctCsrfToken = $this->isCsrfTokenValid(
            'delete_pool',
            $request->get('csrf_token')
        );
        $translator = $this->get('translator');
        $correctConfirmation = ($request->get('confirmation') === $translator->trans('delete.phrase_to_type'));

        if ($correctConfirmation === false || $correctCsrfToken === false) {
            $translator = $this->get('translator');
            $this->get('session')->getFlashBag()->add(
                'error',
                $translator->trans('flashes.delete.not_deleted')
            );

            return $this->redirect($this->generateUrl('pool_manage', array('listUrl' => $listUrl)));
        }

        $em = $this->getDoctrine()->getManager();
        $this->getPool($listUrl);

        $em->remove($this->pool);
        $em->flush();
    }

    /**
     * @Route("/expose/{listUrl}", name="pool_expose")
     * @Template()
     */
    public function exposeAction(Request $request, $listUrl)
    {
        $correctCsrfToken = $this->isCsrfTokenValid(
            'expose_pool',
            $request->get('csrf_token')
        );

        $translator = $this->get('translator');
        $correctConfirmation = ($request->get('confirmation') === $translator->trans('expose.phrase_to_type'));

        if ($correctConfirmation === false || $correctCsrfToken === false) {
            $this->get('session')->getFlashBag()->add(
                'error',
                $translator->trans('flashes.expose.not_exposed')
            );
        } else {
            $this->get('session')->getFlashBag()->add(
                'notice',
                $translator->trans('flashes.expose.exposed')
            );
        }

        /* Tell db pool has been exposed */
        $this->getPool($listUrl);
        $this->pool->expose();

        /* Save db changes */
        $em = $this->getDoctrine()->getManager();
        $em->flush();

        /* Mail pool owner the pool matches */
        /** @var \Intracto\SecretSantaBundle\Entity\EntryService $entryService */
        $entryService = $this->get('intracto_secret_santa.entry_service');
        $entryService->sendPoolMatchesToAdmin($this->pool);

        return $this->redirect($this->generateUrl('pool_manage', array('listUrl' => $listUrl)));
    }

    /**
     * @Route("/resend/{listUrl}/{entryId}", name="pool_resend")
     * @Template("IntractoSecretSantaBundle:Pool:manage.html.twig")
     */
    public function resendAction($listUrl, $entryId)
    {
        $entry = $this->entryRepository->find($entryId);

        if (!is_object($entry)) {
            throw new NotFoundHttpException();
        }

        if ($entry->getPool()->getListUrl() !== $listUrl) {
            throw new NotFoundHttpException();
        }

        $entryService = $this->get('intracto_secret_santa.entry_service');
        $entryService->sendSecretSantaMailForEntry($entry);

        $translator = $this->get('translator');
        $this->get('session')->getFlashBag()->add(
            'success',
            $translator->trans('flashes.resend.resent', array('%email%' => $entry->getName()))
        );

        return $this->redirect($this->generateUrl('pool_manage', array('listUrl' => $listUrl)));
    }

    /**
     * @Route("/forgot-link", name="pool_forgot_manage_link")
     * @Template("IntractoSecretSantaBundle:Pool:forgotLink.html.twig")
     */
    public function forgotLinkAction(Request $request)
    {
        $form = $this->createForm(new ForgotLinkType());

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                if ($this->get('intracto_secret_santa.pool_service')->sendForgotManageLinkMail($form->getData()['email'])) {
                    $feedback = array(
                        'type' => 'success',
                        'message' => $this->get('translator')->trans('flashes.forgot_manage_link.success'),
                    );
                } else {
                    $feedback = array(
                        'type' => 'error',
                        'message' => $this->get('translator')->trans('flashes.forgot_manage_link.error'),
                    );
                }

                $this->addFlash($feedback['type'], $feedback['message']);
            }
        }

        return array(
            'form' => $form->createView(),
        );
    }

    /**
     * Retrieve pool by url
     *
     * @param $listurl
     *
     * @throws NotFoundHttpException
     *
     * @internal param string $url
     *
     * @return bool
     */
    protected function getPool($listurl)
    {
        $this->pool = $this->poolRepository->findOneByListurl($listurl);

        if (!is_object($this->pool)) {
            throw new NotFoundHttpException();
        }

        return true;
    }

    private function handlePoolCreation(Request $request, Pool $pool)
    {
        $form = $this->createForm(new PoolType(), $pool);

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                foreach ($pool->getEntries() as $i => $entry) {
                    if ($i == 0) {
                        $entry->setPoolAdmin(true);
                    }
                    $entry->setPool($pool);
                }
                $em = $this->getDoctrine()->getManager();

                $dateFormatter = \IntlDateFormatter::create($request->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

                $translator = $this->get('translator');
                $message = $translator->trans('emails.created.message', array(
                    '%amount%' => $pool->getAmount(),
                    '%date%' => $dateFormatter->format($pool->getDate()->getTimestamp()),
                    '%message%' => $pool->getMessage(),
                ));

                $pool->setMessage($message);
                $pool->setLocale($request->getLocale());
                $em->persist($pool);
                $em->flush();

                return $this->redirect($this->generateUrl('pool_exclude', array('listUrl' => $pool->getListurl())));
            }
        }

        return array(
            'form' => $form->createView(),
        );
    }
}
