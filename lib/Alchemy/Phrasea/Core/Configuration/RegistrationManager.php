<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2014 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Core\Configuration;

use Alchemy\Phrasea\Model\Repositories\RegistrationRepository;
use Alchemy\Phrasea\Model\Entities\User;
use igorw;

class RegistrationManager
{
    /** @var \appbox */
    private $appbox;
    private $repository;

    public function __construct(\appbox $appbox, RegistrationRepository $repository, $locale)
    {
        $this->appbox = $appbox;
        $this->repository = $repository;
        $this->locale = $locale;
    }

    /**
     * Tells whether registration is enabled or not.
     *
     * @return boolean
     */
    public function isRegistrationEnabled()
    {
        foreach ($this->appbox->get_databoxes() as $databox) {
            foreach ($databox->get_collections() as $collection) {
                if ($collection->isRegistrationEnabled()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Gets information about registration configuration and registration status if a user id is provided.
     *
     * @param null|user $user
     *
     * @return array
     */
    public function getRegistrationSummary(User $user = null)
    {
        $data = $userData = [];

        // Gets user data
        if (null !== $user) {
            $userData = $this->repository->getRegistrationsSummaryForUser($user);
        }

        foreach ($this->appbox->get_databoxes() as $databox) {
            $sbas_id = $databox->get_sbas_id();
            $data[$sbas_id] = [
                // Registrations on databox by type
                'registrations-by-type' => [
                    'active'    => [],
                    'inactive'  => [],
                    'accepted'  => [],
                    'in-time'   => [],
                    'out-dated' => [],
                    'pending'   => [],
                    'rejected'  => [],
                ],
                // Registration configuration on databox and collections that belong to the databox
                'db-name'       => $databox->get_dbname(),
                'cgu'           => $databox->get_cgus(),
                'can-register'  => $databox->isRegistrationEnabled(),
                // Configuration on collection
                'collections'   => [],
                'display'       => false,   // set to true if there is at least one collection to display
            ];

            foreach ($databox->get_collections() as $collection) {
                $base_id = $collection->get_base_id();

                $userRegistration = igorw\get_in($userData, [$sbas_id, $base_id]);

                // Sets collection info
                $data[$sbas_id]['collections'][$base_id] = [
                    'coll-name'     => $collection->get_label($this->locale),
                    // gets collection registration or fallback to databox configuration
                    'can-register'  => $collection->isRegistrationEnabled(),
                    // boolean to tell whether user has already requested an access to the collection
                    'registration'  => !is_null($userRegistration) && !is_null($userRegistration['active']),
                    'type'          => null
                ];

                // Sets registration by type
                if (!is_null($userRegistration)) { //  && !is_null($userRegistration['active'])) {

                    $userRegistration['coll-name'] = $collection->get_label($this->locale);
                    $userRegistration['can-register'] = $collection->isRegistrationEnabled();
                    // sets default type
                    $type = 'inactive';

                    // gets registration entity
                    $registration = $userRegistration['registration'];

                    if(!is_null($userRegistration['active'])) {
                        // rights are set in basusr, we don't really care about registration
                        $isTimeLimited = (Boolean) $userRegistration['time-limited'];
                        if($isTimeLimited) {
                            // any time limit overrides (=automates) the 'active' value
                            $isOnTime = (Boolean) $userRegistration['in-time'];
                            $type = $isOnTime ? 'in-time' : 'out-dated';
                        }
                        else {
                            // no time limit, use the 'active' value - but be nice if this is the result of registration
                            $isPending  = !is_null($registration) && $registration->isPending();
                            $isRejected = !is_null($registration) && !$isPending && $registration->isRejected();
                            $isAccepted = !is_null($registration) && !$isPending && !$isRejected;
                            if ($userRegistration['active'] === false) {
                                // no access
                                $type = $isRejected ? 'rejected' : 'inactive';
                            }
                            else {
                                // access
                                $type = $isAccepted ? 'accepted' : 'active';
                            }
                        }
                    }
                    else {
                        // nothing in basusr, use only registration
                        if(is_null($registration)) {
                            // no registration
                            $type = 'inactive';
                        }
                        else {
                            // something in registration
                            $isPending  = $registration->isPending();
                            $isRejected = !$isPending && $registration->isRejected();
                            if($isPending) {
                                $type = 'pending';
                            }
                            else {
                                $type = $isRejected ? 'rejected' : 'accepted';
                            }
                        }
                    }

                    // the twig template will not display an inactive collection, unless it is registrable
                    if($type !== 'inactive' || $collection->isRegistrationEnabled()) {
                        // at least one collection is displayed so the dbox must be displayed
                        $data[$sbas_id]['display'] = true;
                    }

                    $userRegistration['type'] = $type;
                    $data[$sbas_id]['collections'][$base_id]['type'] = $type;
                    $data[$sbas_id]['registrations-by-type'][$type][] = $userRegistration;
                }
            }
        }

        return $data;
    }

    /**
     * Tells whether user has ever requested a registration on collection or not.
     *
     * @param \collection $collection
     * @param             $userData
     *
     * @return boolean
     */
    private function userHasRequestedARegistrationOnCollection(\collection $collection, $userData)
    {
        if (null === $userRegistration = igorw\get_in($userData, [$collection->get_sbas_id(), $collection->get_base_id()])) {
            return false;
        }

        return !is_null($userRegistration['active']);
    }

    /**
     * Returns a user registration for given collection or null if no registration were requested.
     *
     * @param \collection $collection
     * @param             $userData
     *
     * @return null|array
     */
    private function getUserCollectionRegistration(\collection $collection, $userData)
    {
        if (false === $this->userHasRequestedARegistrationOnCollection($collection, $userData)) {
            return null;
        }

        $userRegistration = igorw\get_in($userData, [$collection->get_sbas_id(), $collection->get_base_id()]);

        // sets collection name
        $userRegistration['coll-name'] = $collection->get_label($this->locale);
        // sets default type
        $userRegistration['type'] = 'active';

        // gets registration entity
        $registration = $userRegistration['registration'];

        // set registration type & return user registration
        $registrationStillExists = !is_null($registration);
        $registrationNoMoreExists = !$registrationStillExists;
        $isPending = $registrationStillExists && $registration->isPending() && !$registration->isRejected();
        $isRejected = $registrationStillExists && $registration->isRejected();
        $isDone = ($registrationNoMoreExists) || (!$isPending && !$isRejected);
        $isActive = (Boolean) $userRegistration['active'];
        $isTimeLimited = (Boolean) $userRegistration['time-limited'];
        $isNotTimeLimited = !$isTimeLimited;
        $isOnTime = (Boolean) $userRegistration['in-time'];
        $isOutDated = !$isOnTime;

        if (!$isActive) {
            $userRegistration['type'] = 'inactive';

            return $userRegistration;
        }

        if ($isDone) {
            $userRegistration['type'] = 'accepted';

            return $userRegistration;
        }

        if ($isRejected) {
            $userRegistration['type'] = 'rejected';

            return $userRegistration;
        }

        if ($isTimeLimited && $isOnTime && $isPending) {
            $userRegistration['type'] = 'in-time';

            return $userRegistration;
        }

        if ($isTimeLimited && $isOutDated && $isPending) {
            $userRegistration['type'] = 'out-dated';

            return  $userRegistration;
        }

        if ($isNotTimeLimited && $isPending) {
            $userRegistration['type'] = 'pending';

            return $userRegistration;
        }

        return $userRegistration;
    }

    private function getCollectionSummary(\collection $collection, $userData)
    {
        return [
            'coll-name'     => $collection->get_label($this->locale),
            // gets collection registration or fallback to databox configuration
            'can-register'  => $collection->isRegistrationEnabled(),
            'cgu'           => $collection->getTermsOfUse(),
            // boolean to tell whether user has already requested an access to the collection
            'registration'  => $this->userHasRequestedARegistrationOnCollection($collection, $userData)
        ];
    }
}
