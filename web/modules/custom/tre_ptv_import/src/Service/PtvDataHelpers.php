<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service;

use Drupal\tre_ptv_import\PtvDataHelpersInterface;

/**
 * Data helper functions for dealing with the PTV API data.
 */
class PtvDataHelpers implements PtvDataHelpersInterface {

  /**
   * Maps PTV weekday enum values to Office Hours weekday values.
   */
  private const WEEKDAY_TO_OFFICE_HOURS_DAY = [
    'Monday' => 1,
    'Tuesday' => 2,
    'Wednesday' => 3,
    'Thursday' => 4,
    'Friday' => 5,
    'Saturday' => 6,
    'Sunday' => 0,
  ];

  /**
   * {@inheritDoc}
   */
  public static function processServiceHours(array &$values, mixed $serviceHours, string $language): void {
    $values['regular_daily_hours_hours'] = [];
    $values['regular_overnight_hours_hours'] = [];
    $values['exception_hours_info'] = [];

    if (!is_object($serviceHours)) {
      return;
    }

    $daily = [];
    $overnight = [];
    $exceptions = [];

    foreach (self::getArrayFromGetter($serviceHours, 'getHours') as $hour) {
      if (!is_object($hour)) {
        continue;
      }

      $type = self::getStringFromGetter($hour, 'getType');

      switch ($type) {
        case 'ContinuousAlwaysValidServiceHour':
          $status = self::getStringFromGetter($hour, 'getOpeningStatus');
          if ($status !== NULL && $status !== 'Closed') {
            $comment = self::buildComment(
              self::getLocalizedString($hour, 'getAdditionalInformation', $language),
              $status,
              $language
            );

            foreach (range(0, 6) as $day) {
              $daily[] = [
                'day' => $day,
                'starthours' => '0',
                'endhours' => '0',
                'comment' => $comment,
              ];
            }
          }
          break;

        case 'ContinuousWeekdaysUntilFurtherNoticeServiceHour':
          $status = self::getStringFromGetter($hour, 'getOpeningStatus');
          $fromDay = self::getStringFromGetter($hour, 'getFromDay');
          $toDay = self::getStringFromGetter($hour, 'getToDay');
          $start = self::getStringFromGetter($hour, 'getFromTime');
          $end = self::getStringFromGetter($hour, 'getToTime');

          if (
            $status !== NULL && $status !== 'Closed'
            && $fromDay !== NULL && $toDay !== NULL
            && $start !== NULL && $end !== NULL
          ) {
            $comment = self::buildComment(
              self::getLocalizedString($hour, 'getAdditionalInformation', $language),
              $status,
              $language
            );

            foreach (self::getWeekdayRange($fromDay, $toDay) as $day) {
              self::addOfficeHoursInterval($daily, $overnight, $day, $start, $end, $comment);
            }
          }
          break;

        case 'WeeklyUntilFurtherNoticeServiceHour':
          self::appendWeeklyHours(
            $daily,
            $overnight,
            self::getObjectFromGetter($hour, 'getWeeklyHours'),
            $language
          );
          break;

        case 'WeeklyDateRangeServiceHour':
        case 'ExceptionalDateRangeServiceHour':
        case 'ContinuousDateRangeServiceHour':
          $exception = self::buildDateRangeException($hour, $language);
          if ($exception !== NULL) {
            $exceptions[] = $exception;
          }
          break;
      }
    }

    $holidayHours = self::getObjectFromGetter($serviceHours, 'getHolidayServiceHours');
    if ($holidayHours !== NULL) {
      foreach (self::buildHolidayExceptions($holidayHours, $language) as $exception) {
        $exceptions[] = $exception;
      }
    }

    $daily = array_values(array_unique($daily, SORT_REGULAR));
    $overnight = array_values(array_unique($overnight, SORT_REGULAR));

    if ($daily !== []) {
      $values['regular_daily_hours_hours'] = [$daily];
    }
    if ($overnight !== []) {
      $values['regular_overnight_hours_hours'] = [$overnight];
    }

    usort($exceptions, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);
    $values['exception_hours_info'] = array_values(array_unique(array_column($exceptions, 'info')));
  }

  /**
   * Appends weekly service hours to regular Office Hours source rows.
   */
  private static function appendWeeklyHours(
    array &$daily,
    array &$overnight,
    ?object $weeklyHours,
    string $language,
  ): void {
    if ($weeklyHours === NULL) {
      return;
    }

    foreach (self::WEEKDAY_TO_OFFICE_HOURS_DAY as $weekday => $day) {
      $weekdayHours = self::getObjectFromGetter($weeklyHours, 'get' . $weekday);
      if ($weekdayHours === NULL) {
        continue;
      }

      foreach (self::getArrayFromGetter($weekdayHours, 'getHours') as $entry) {
        if (!is_object($entry)) {
          continue;
        }

        $status = self::getStringFromGetter($entry, 'getOpeningStatus');
        $start = self::getStringFromGetter($entry, 'getStart');
        $end = self::getStringFromGetter($entry, 'getEnd');

        if ($status === NULL || $status === 'Closed' || $start === NULL || $end === NULL) {
          continue;
        }

        self::addOfficeHoursInterval(
          $daily,
          $overnight,
          $day,
          $start,
          $end,
          self::buildComment(
            self::getLocalizedString($entry, 'getAdditionalInformation', $language),
            $status,
            $language
          )
        );
      }
    }
  }

  /**
   * Adds one time interval to daily or overnight Office Hours source rows.
   */
  private static function addOfficeHoursInterval(
    array &$daily,
    array &$overnight,
    int $day,
    string $start,
    string $end,
    string $comment,
  ): void {
    $startValue = self::formatOfficeHoursTime($start);
    $endValue = self::formatOfficeHoursTime($end);

    if ($startValue === NULL || $endValue === NULL) {
      return;
    }

    if ($start <= $end) {
      $daily[] = [
        'day' => $day,
        'starthours' => $startValue,
        'endhours' => $endValue,
        'comment' => $comment,
      ];
      return;
    }

    $overnight[] = [
      'day' => $day,
      'starthours' => $startValue,
      'endhours' => '0000',
      'comment' => $comment,
    ];
    $overnight[] = [
      'day' => $day === 6 ? 0 : $day + 1,
      'starthours' => '0000',
      'endhours' => $endValue,
      'comment' => $comment,
    ];
  }

  /**
   * Formats one date-range service hour for field_exception_hours.
   *
   * @return array{sort: string, info: string}|null
   *   A sortable exception field value, or NULL when it has no usable text.
   */
  private static function buildDateRangeException(object $hour, string $language): ?array {
    $type = self::getStringFromGetter($hour, 'getType');
    $fromDate = self::parseDate(self::getValueFromGetter($hour, 'getFromDate'));
    $toDate = self::parseDate(self::getValueFromGetter($hour, 'getToDate'));
    $description = self::joinTextParts([
      self::getLocalizedString($hour, 'getHeading', $language),
      self::getLocalizedString($hour, 'getAdditionalInformation', $language),
    ]);

    if ($type === 'ContinuousDateRangeServiceHour') {
      $schedule = self::buildContinuousHoursText($hour, $language);
    }
    else {
      $schedule = self::buildWeeklyHoursText(
        self::getObjectFromGetter($hour, 'getWeeklyHours'),
        $language,
        $type === 'WeeklyDateRangeServiceHour'
          || ($fromDate !== NULL && $toDate !== NULL && $fromDate->format('Y-m-d') !== $toDate->format('Y-m-d'))
      );
    }

    $info = self::joinTextParts([
      $description,
      self::buildDateRangeText($fromDate, $toDate),
      $schedule,
    ]);

    if ($info === '') {
      return NULL;
    }

    return [
      'sort' => $fromDate?->format('Y-m-d') ?? '0000-00-00',
      'info' => $info,
    ];
  }

  /**
   * Formats holiday service hours for field_exception_hours.
   *
   * @return array<int, array{sort: string, info: string}>
   *   Holiday exception values.
   */
  private static function buildHolidayExceptions(object $holidayHours, string $language): array {
    $exceptions = [];

    foreach (self::getArrayFromGetter($holidayHours, 'getHolidays') as $holiday) {
      if (!is_object($holiday)) {
        continue;
      }

      $name = self::getStringFromGetter($holiday, 'getHolidayName');
      if ($name === NULL) {
        continue;
      }

      $times = [];
      foreach (self::getArrayFromGetter($holiday, 'getHours') as $hour) {
        if (!is_object($hour)) {
          continue;
        }

        $time = self::buildStatusTimeText(
          self::getStringFromGetter($hour, 'getStart'),
          self::getStringFromGetter($hour, 'getEnd'),
          self::getStringFromGetter($hour, 'getOpeningStatus'),
          self::getLocalizedString($hour, 'getAdditionalInformation', $language),
          $language
        );
        if ($time !== '') {
          $times[] = $time;
        }
      }

      $exceptions[] = [
        'sort' => '9999-' . $name,
        'info' => self::joinTextParts([
          self::getHolidayLabel($name, $language),
          implode(', ', array_unique($times)),
        ]),
      ];
    }

    return array_filter($exceptions, static fn (array $item): bool => $item['info'] !== '');
  }

  /**
   * Formats weekly hours as exception text.
   */
  private static function buildWeeklyHoursText(
    ?object $weeklyHours,
    string $language,
    bool $showWeekdays,
  ): string {
    if ($weeklyHours === NULL) {
      return '';
    }

    $days = [];
    foreach (self::WEEKDAY_TO_OFFICE_HOURS_DAY as $weekday => $day) {
      $weekdayHours = self::getObjectFromGetter($weeklyHours, 'get' . $weekday);
      if ($weekdayHours === NULL) {
        continue;
      }

      $times = [];
      foreach (self::getArrayFromGetter($weekdayHours, 'getHours') as $hour) {
        if (!is_object($hour)) {
          continue;
        }

        $time = self::buildStatusTimeText(
          self::getStringFromGetter($hour, 'getStart'),
          self::getStringFromGetter($hour, 'getEnd'),
          self::getStringFromGetter($hour, 'getOpeningStatus'),
          self::getLocalizedString($hour, 'getAdditionalInformation', $language),
          $language
        );
        if ($time !== '') {
          $times[] = $time;
        }
      }

      if ($times !== []) {
        $days[$weekday] = array_values(array_unique($times));
      }
    }

    if ($days === []) {
      return '';
    }

    if (count($days) === 1 && !$showWeekdays) {
      return implode(', ', reset($days));
    }

    $result = [];
    foreach ($days as $weekday => $times) {
      $result[] = self::getWeekdayLabel($weekday, $language)
        . ' '
        . implode(', ', $times);
    }

    return implode(', ', $result);
  }

  /**
   * Formats a continuous date range service hour as exception text.
   */
  private static function buildContinuousHoursText(object $hour, string $language): string {
    $time = self::buildStatusTimeText(
      self::getStringFromGetter($hour, 'getFromTime'),
      self::getStringFromGetter($hour, 'getToTime'),
      self::getStringFromGetter($hour, 'getOpeningStatus'),
      NULL,
      $language
    );
    $fromDay = self::getStringFromGetter($hour, 'getFromDay');
    $toDay = self::getStringFromGetter($hour, 'getToDay');

    if ($time === '' || $fromDay === NULL || $toDay === NULL) {
      return $time;
    }

    $days = $fromDay === $toDay
      ? self::getWeekdayLabel($fromDay, $language)
      : self::getWeekdayLabel($fromDay, $language) . '–' . self::getWeekdayLabel($toDay, $language);

    return $days . ' ' . $time;
  }

  /**
   * Formats a status and time interval for display.
   */
  private static function buildStatusTimeText(
    ?string $start,
    ?string $end,
    ?string $status,
    ?string $additionalInformation,
    string $language,
  ): string {
    if ($status === 'Closed') {
      return self::joinTextParts([
        self::getClosedLabel($language),
        $additionalInformation,
      ]);
    }

    $time = self::buildTimeText($start, $end);
    return self::joinTextParts([
      $time,
      $status === 'OpenOnReservation' ? self::getReservationLabel($language) : NULL,
      $additionalInformation,
    ]);
  }

  /**
   * Formats a date range for display.
   */
  private static function buildDateRangeText(
    ?\DateTimeImmutable $from,
    ?\DateTimeImmutable $to,
  ): string {
    if ($from === NULL && $to === NULL) {
      return '';
    }
    if ($from !== NULL && $to !== NULL && $from->format('Y-m-d') !== $to->format('Y-m-d')) {
      return $from->format('j.n.Y') . '—' . $to->format('j.n.Y');
    }

    return ($from ?? $to)->format('j.n.Y');
  }

  /**
   * Formats a time interval for display.
   */
  private static function buildTimeText(?string $start, ?string $end): string {
    $start = self::formatDisplayTime($start);
    $end = self::formatDisplayTime($end);

    if ($start === NULL) {
      return $end ?? '';
    }
    if ($end === NULL) {
      return $start;
    }

    return $start . '–' . $end;
  }

  /**
   * Converts HH:MM to an Office Hours source value.
   */
  private static function formatOfficeHoursTime(string $time): ?string {
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
      return NULL;
    }

    return str_pad((string) (int) $matches[1], 2, '0', STR_PAD_LEFT)
      . $matches[2];
  }

  /**
   * Converts HH:MM to a display value.
   */
  private static function formatDisplayTime(?string $time): ?string {
    if ($time === NULL || !preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
      return NULL;
    }

    return (int) $matches[1] . '.' . $matches[2];
  }

  /**
   * Gets all weekday numbers in an inclusive weekday range.
   *
   * @return int[]
   *   Weekday numbers where Sunday is 0 and Saturday is 6.
   */
  private static function getWeekdayRange(string $from, string $to): array {
    if (!isset(self::WEEKDAY_TO_OFFICE_HOURS_DAY[$from], self::WEEKDAY_TO_OFFICE_HOURS_DAY[$to])) {
      return [];
    }

    $days = [];
    $day = self::WEEKDAY_TO_OFFICE_HOURS_DAY[$from];
    $end = self::WEEKDAY_TO_OFFICE_HOURS_DAY[$to];

    while (TRUE) {
      $days[] = $day;
      if ($day === $end) {
        break;
      }
      $day = $day === 6 ? 0 : $day + 1;
    }

    return $days;
  }

  /**
   * Builds an Office Hours comment from service hour metadata.
   */
  private static function buildComment(?string $additionalInformation, ?string $status, string $language): string {
    return self::joinTextParts([
      $additionalInformation,
      $status === 'OpenOnReservation' ? self::getReservationLabel($language) : NULL,
    ]);
  }

  /**
   * Gets a getter value from a generated DTO.
   */
  private static function getValueFromGetter(object $model, string $getter): mixed {
    return method_exists($model, $getter) ? $model->{$getter}() : NULL;
  }

  /**
   * Gets an object value from a generated DTO getter.
   */
  private static function getObjectFromGetter(object $model, string $getter): ?object {
    $value = self::getValueFromGetter($model, $getter);
    return is_object($value) ? $value : NULL;
  }

  /**
   * Gets an array value from a generated DTO getter.
   *
   * @return array<int, mixed>
   *   The getter value or an empty array.
   */
  private static function getArrayFromGetter(object $model, string $getter): array {
    $value = self::getValueFromGetter($model, $getter);
    return is_array($value) ? $value : [];
  }

  /**
   * Gets a normalized string value from a generated DTO getter.
   */
  private static function getStringFromGetter(object $model, string $getter): ?string {
    $value = self::getValueFromGetter($model, $getter);
    if (!is_string($value)) {
      return NULL;
    }

    $value = trim($value);
    return $value === '' ? NULL : $value;
  }

  /**
   * Gets a localized string from a generated DTO.
   */
  private static function getLocalizedString(object $model, string $getter, string $language): ?string {
    $localized = self::getObjectFromGetter($model, $getter);
    $localizedGetter = match ($language) {
      'fi' => 'getFi',
      'en' => 'getEn',
      'sv' => 'getSv',
      default => NULL,
    };

    return $localized === NULL || $localizedGetter === NULL
      ? NULL
      : self::getStringFromGetter($localized, $localizedGetter);
  }

  /**
   * Converts a date getter value to an immutable date.
   */
  private static function parseDate(mixed $value): ?\DateTimeImmutable {
    if ($value instanceof \DateTimeImmutable) {
      return $value;
    }
    if ($value instanceof \DateTimeInterface) {
      return \DateTimeImmutable::createFromInterface($value);
    }
    if (!is_string($value) || trim($value) === '') {
      return NULL;
    }

    try {
      return new \DateTimeImmutable($value);
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Joins non-empty text values without duplicates.
   */
  private static function joinTextParts(array $parts): string {
    $values = [];
    foreach ($parts as $part) {
      if (is_string($part) && trim($part) !== '') {
        $values[trim($part)] = trim($part);
      }
    }

    return implode(' ', array_values($values));
  }

  /**
   * Gets a localized weekday label.
   */
  private static function getWeekdayLabel(string $weekday, string $language): string {
    $labels = [
      'fi' => [
        'Monday' => 'maanantai',
        'Tuesday' => 'tiistai',
        'Wednesday' => 'keskiviikko',
        'Thursday' => 'torstai',
        'Friday' => 'perjantai',
        'Saturday' => 'lauantai',
        'Sunday' => 'sunnuntai',
      ],
      'en' => [
        'Monday' => 'Monday',
        'Tuesday' => 'Tuesday',
        'Wednesday' => 'Wednesday',
        'Thursday' => 'Thursday',
        'Friday' => 'Friday',
        'Saturday' => 'Saturday',
        'Sunday' => 'Sunday',
      ],
      'sv' => [
        'Monday' => 'måndag',
        'Tuesday' => 'tisdag',
        'Wednesday' => 'onsdag',
        'Thursday' => 'torsdag',
        'Friday' => 'fredag',
        'Saturday' => 'lördag',
        'Sunday' => 'söndag',
      ],
    ];

    return $labels[$language][$weekday] ?? $labels['fi'][$weekday] ?? $weekday;
  }

  /**
   * Gets a localized closed label.
   */
  private static function getClosedLabel(string $language): string {
    return [
      'fi' => 'suljettu',
      'en' => 'closed',
      'sv' => 'stängt',
    ][$language] ?? 'suljettu';
  }

  /**
   * Gets a localized appointment-only label.
   */
  private static function getReservationLabel(string $language): string {
    return [
      'fi' => 'ajanvarauksella',
      'en' => 'by appointment',
      'sv' => 'med tidsbokning',
    ][$language] ?? 'ajanvarauksella';
  }

  /**
   * Gets a localized holiday label.
   */
  private static function getHolidayLabel(string $holiday, string $language): string {
    $labels = [
      'fi' => [
        'NewYearDay' => 'uudenvuodenpäivä',
        'Epiphany' => 'loppiainen',
        'MaundyThursday' => 'kiirastorstai',
        'GoodFriday' => 'pitkäperjantai',
        'EasterSunday' => 'pääsiäispäivä',
        'EasterMonday' => '2. pääsiäispäivä',
        'MayDayEve' => 'vapun aatto',
        'MayDay' => 'vappu',
        'AscensionDay' => 'helatorstai',
        'WhitSunday' => 'helluntaipäivä',
        'MidsummerEve' => 'juhannusaatto',
        'MidsummerDay' => 'juhannuspäivä',
        'AllSaintsDay' => 'pyhäinpäivä',
        'IndependenceDay' => 'itsenäisyyspäivä',
        'ChristmasEve' => 'jouluaatto',
        'ChristmasDay' => 'joulupäivä',
        'SecondDayOfChristmas' => 'tapaninpäivä',
        'NewYearEve' => 'uudenvuodenaatto',
      ],
      'en' => [
        'NewYearDay' => 'New Year’s Day',
        'Epiphany' => 'Epiphany',
        'MaundyThursday' => 'Maundy Thursday',
        'GoodFriday' => 'Good Friday',
        'EasterSunday' => 'Easter Sunday',
        'EasterMonday' => 'Easter Monday',
        'MayDayEve' => 'May Day Eve',
        'MayDay' => 'May Day',
        'AscensionDay' => 'Ascension Day',
        'WhitSunday' => 'Whit Sunday',
        'MidsummerEve' => 'Midsummer Eve',
        'MidsummerDay' => 'Midsummer Day',
        'AllSaintsDay' => 'All Saints’ Day',
        'IndependenceDay' => 'Independence Day',
        'ChristmasEve' => 'Christmas Eve',
        'ChristmasDay' => 'Christmas Day',
        'SecondDayOfChristmas' => 'Second Day of Christmas',
        'NewYearEve' => 'New Year’s Eve',
      ],
      'sv' => [
        'NewYearDay' => 'nyårsdagen',
        'Epiphany' => 'trettondedagen',
        'MaundyThursday' => 'skärtorsdagen',
        'GoodFriday' => 'långfredagen',
        'EasterSunday' => 'påskdagen',
        'EasterMonday' => 'annandag påsk',
        'MayDayEve' => 'valborgsmässoafton',
        'MayDay' => 'första maj',
        'AscensionDay' => 'Kristi himmelsfärdsdag',
        'WhitSunday' => 'pingstdagen',
        'MidsummerEve' => 'midsommarafton',
        'MidsummerDay' => 'midsommardagen',
        'AllSaintsDay' => 'alla helgons dag',
        'IndependenceDay' => 'självständighetsdagen',
        'ChristmasEve' => 'julafton',
        'ChristmasDay' => 'juldagen',
        'SecondDayOfChristmas' => 'annandag jul',
        'NewYearEve' => 'nyårsafton',
      ],
    ];

    return $labels[$language][$holiday] ?? $labels['fi'][$holiday] ?? $holiday;
  }

}