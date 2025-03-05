<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Listener;

use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

/** @template-implements IEventListener<CalendarObjectCreatedEvent|CalendarObjectUpdatedEvent> */
class CalDavEventListener implements IEventListener {

	public function __construct(
		protected Manager $manager,
		protected RoomService $roomService,
		protected IUser $user,
		protected LoggerInterface $logger,
	) {

	}

	public function handle(Event $event): void {
		if (!$event instanceof CalendarObjectCreatedEvent && !$event instanceof CalendarObjectUpdatedEvent) {
			return;
		}

		$calData = $event->getObjectData()['calendardata'] ?? null;
		if (!$calData) {
			return;
		}

		if (!str_contains($calData, 'LOCATION:')) {
			return;
		}

		$vobject = Reader::read($calData);
		$vevent = $vobject->VEVENT;
		// Check if the location is set and if the location string contains a call url
		$location = $vevent->LOCATION->getValue();
		if ($location === null || !str_contains($location, '/call/')) {
			return;
		}

		// Check if room exists and check if user is part of room
		$roomToken = array_reverse(explode('/', $location))[0];
		try {
			$room = $this->manager->getRoomByToken($roomToken, $this->user->getUID());
		} catch (RoomNotFoundException $e) {
			$this->logger->debug('Room not found: ' . $e->getMessage());
			return;
		}

		// get room type and if it is not Room Object Event, return
		if ($room->getType() !== Room::OBJECT_TYPE_EVENT) {
			$this->logger->debug("Room $roomToken not an event room");
			return;
		}

		$rrule = $vevent->RRULE->getValue();
		// We don't handle rooms with RRULEs
		if (!empty($rrule)) {
//			$this->roomService->resetObject($room);
			$this->logger->debug("Room $roomToken calendar event contains an RRRULE, converting to regular room");
			return;
		}

		$start = $vevent->DTSTART;


		// Get starttime
		// update object id for room so it's the start timestamp
	}
}
