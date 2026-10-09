<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Migration;

use Yasumi\Yasumi;

/**
 * Maps PTV API service hours to the existing migration source format.
 */
final class PtvServiceHoursMapper
{

  /**
   * Maps API weekday values to Office Hours weekday values.
   */
  private const WEEKDAYS = [
    'Monday' => 1,
    'Tuesday' => 2,
    'Wednesday' => 3,
    'Thursday' => 4,
    'Friday' => 5,
    'Saturday' => 6,
    'Sunday' => 0,
  ];

  /**
   * Maps supported language codes to generated DTO getter names.
   */
  private const LANGUAGE_GETTERS = [
    'fi' => 'getFi',
    'en' => 'getEn',
  ];

  /**
   * Maps PTV holiday names to Yasumi Finland holiday keys.
   */
  private const YASUMI_HOLIDAY_KEYS = [
    'NewYearDay' => 'newYearsDay',
    'Epiphany' => 'epiphany',
    'GoodFriday' => 'goodFriday',
    'EasterSunday' => 'easter',
    'EasterMonday' => 'easterMonday',
    'MayDay' => 'internationalWorkersDay',
    'AscensionDay' => 'ascensionDay',
    'WhitSunday' => 'pentecost',
    'MidsummerDay' => 'stJohnsDay',
    'AllSaintsDay' => 'allSaintsDay',
    'IndependenceDay' => 'independenceDay',
    'ChristmasDay' => 'christmasDay',
    'SecondDayOfChristmas' => 'secondChristmasDay',
  ];

  /**
   * Maps service hours into migration source values.
   */
  public function map(
    array &$values,
    mixed $serviceHours,
    string $language,
  ): void {
    $values['regular_daily_hours_hours'] = [];
    $values['regular_daily_hours_info'] = [];
    $values['regular_overnight_hours_hours'] = [];
    $values['regular_overnight_hours_info'] = [];
    $values['exception_hours_info'] = [];

    $language = trim($language);

    if ($language === '') {
      throw new \InvalidArgumentException(
        'The requested service hours language cannot be empty.'
      );
    }

    if (!is_object($serviceHours)) {
      return;
    }

    $regularDailyGroups = [];
    $regularDailyInfo = [];
    $regularOvernightGroups = [];
    $regularOvernightInfo = [];
    $exceptions = [];
    $today = new \DateTimeImmutable(
      'today',
      new \DateTimeZone('Europe/Helsinki')
    );

    $hours = self::getArray($serviceHours, 'getHours');

    // Keep regular opening hour groups in chronological order.
    // Hour types without fromDate are placed first.
    usort(
      $hours,
      static function (mixed $a, mixed $b): int {
        if (!is_object($a) || !is_object($b)) {
          return 0;
        }

        $aDate = self::getDate(self::getValue($a, 'getFromDate'));
        $bDate = self::getDate(self::getValue($b, 'getFromDate'));

        $aSort = $aDate?->format('Y-m-d') ?? '0000-00-00';
        $bSort = $bDate?->format('Y-m-d') ?? '0000-00-00';

        return $aSort <=> $bSort;
      }
    );

    foreach ($hours as $hour) {
      if (!is_object($hour)) {
        continue;
      }

      $toDate = self::getDate(self::getValue($hour, 'getToDate'));
      if ($toDate !== NULL && $toDate->format('Y-m-d') < $today->format('Y-m-d')) {
        continue;
      }

      $type = self::getString($hour, 'getType');
      $heading = self::getLocalizedString(
        $hour,
        'getHeading',
        $language
      );

      switch ($type) {
        case 'ContinuousAlwaysValidServiceHour':
          [$daily, $overnight] = self::mapContinuousAlwaysValidHours(
            $hour,
            $language
          );

          self::appendRegularGroups(
            $regularDailyGroups,
            $regularDailyInfo,
            $regularOvernightGroups,
            $regularOvernightInfo,
            $daily,
            $overnight,
            $heading
          );
          break;

        case 'ContinuousWeekdaysUntilFurtherNoticeServiceHour':
          [$daily, $overnight] = self::mapContinuousWeekdayHours(
            $hour,
            $language
          );

          self::appendRegularGroups(
            $regularDailyGroups,
            $regularDailyInfo,
            $regularOvernightGroups,
            $regularOvernightInfo,
            $daily,
            $overnight,
            $heading
          );
          break;

        case 'WeeklyUntilFurtherNoticeServiceHour':
        case 'WeeklyDateRangeServiceHour':
          [$daily, $overnight] = self::mapWeeklyRegularHours(
            self::getObject($hour, 'getWeeklyHours'),
            $language
          );

          self::appendRegularGroups(
            $regularDailyGroups,
            $regularDailyInfo,
            $regularOvernightGroups,
            $regularOvernightInfo,
            $daily,
            $overnight,
            $heading
          );
          break;

        case 'ExceptionalDateRangeServiceHour':
        case 'ContinuousDateRangeServiceHour':
          $exception = self::mapDateRangeException(
            $hour,
            $language,
            $today
          );

          if ($exception !== NULL) {
            $exceptions[] = $exception;
          }
          break;
      }
    }

    $holidayHours = self::getObject(
      $serviceHours,
      'getHolidayServiceHours'
    );

    if ($holidayHours !== NULL) {
      foreach (
        self::mapHolidayExceptions($holidayHours, $language, $today)
        as $exception
      ) {
        $exceptions[] = $exception;
      }
    }

    $values['regular_daily_hours_hours'] = $regularDailyGroups;
    $values['regular_daily_hours_info'] = $regularDailyInfo;
    $values['regular_overnight_hours_hours'] = $regularOvernightGroups;
    $values['regular_overnight_hours_info'] = $regularOvernightInfo;

    usort(
      $exceptions,
      static function (array $a, array $b): int {
        if ($a['is_past'] !== $b['is_past']) {
          return $a['is_past'] ? 1 : -1;
        }

        if ($a['sort'] === NULL || $b['sort'] === NULL) {
          if ($a['sort'] === $b['sort']) {
            return 0;
          }

          return $a['sort'] === NULL ? 1 : -1;
        }

        return $a['is_past']
          ? $b['sort'] <=> $a['sort']
          : $a['sort'] <=> $b['sort'];
      }
    );

    $values['exception_hours_info'] = array_values(
      array_unique(
        array_column($exceptions, 'info')
      )
    );
  }

  /**
   * Maps one always-valid service hour block into Office Hours groups.
   *
   * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
   *   Daily and overnight Office Hours rows.
   */
  private static function mapContinuousAlwaysValidHours(
    object $hour,
    string $language,
  ): array {
    $daily = [];
    $overnight = [];
    $status = self::getString($hour, 'getOpeningStatus');

    if ($status === NULL || $status === 'Closed') {
      return [$daily, $overnight];
    }

    $comment = self::buildComment(
      self::getLocalizedString(
        $hour,
        'getAdditionalInformation',
        $language
      ),
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

    return [$daily, $overnight];
  }

  /**
   * Maps one continuous weekday service hour block into Office Hours groups.
   *
   * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
   *   Daily and overnight Office Hours rows.
   */
  private static function mapContinuousWeekdayHours(
    object $hour,
    string $language,
  ): array {
    $daily = [];
    $overnight = [];

    $status = self::getString($hour, 'getOpeningStatus');
    $fromDay = self::getString($hour, 'getFromDay');
    $toDay = self::getString($hour, 'getToDay');
    $start = self::getString($hour, 'getFromTime');
    $end = self::getString($hour, 'getToTime');

    if (
      $status === NULL
      || $status === 'Closed'
      || $fromDay === NULL
      || $toDay === NULL
      || $start === NULL
      || $end === NULL
    ) {
      return [$daily, $overnight];
    }

    $comment = self::buildComment(
      self::getLocalizedString(
        $hour,
        'getAdditionalInformation',
        $language
      ),
      $status,
      $language
    );

    foreach (self::getDayRange($fromDay, $toDay) as $day) {
      self::addInterval(
        $daily,
        $overnight,
        $day,
        $start,
        $end,
        $comment
      );
    }

    return [$daily, $overnight];
  }

  /**
   * Maps one weekly service hour block into Office Hours groups.
   *
   * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
   *   Daily and overnight Office Hours rows.
   */
  private static function mapWeeklyRegularHours(
    ?object $weeklyHours,
    string $language,
  ): array {
    $daily = [];
    $overnight = [];

    if ($weeklyHours === NULL) {
      return [$daily, $overnight];
    }

    foreach (self::WEEKDAYS as $weekday => $day) {
      $weekdayHours = self::getObject(
        $weeklyHours,
        'get' . $weekday
      );

      if ($weekdayHours === NULL) {
        continue;
      }

      foreach (self::getArray($weekdayHours, 'getHours') as $entry) {
        if (!is_object($entry)) {
          continue;
        }

        $status = self::getString($entry, 'getOpeningStatus');
        $start = self::getString($entry, 'getStart');
        $end = self::getString($entry, 'getEnd');

        if (
          $status === NULL
          || $status === 'Closed'
          || $start === NULL
          || $end === NULL
        ) {
          continue;
        }

        self::addInterval(
          $daily,
          $overnight,
          $day,
          $start,
          $end,
          self::buildComment(
            self::getLocalizedString(
              $entry,
              'getAdditionalInformation',
              $language
            ),
            $status,
            $language
          )
        );
      }
    }

    return [$daily, $overnight];
  }

  /**
   * Appends one regular service hour block as its own migration source group.
   *
   * @param array<int, array<int, array<string, mixed>>> $dailyGroups
   *   Current daily Office Hours groups.
   * @param array<int, string|null> $dailyInfo
   *   Current daily group headings.
   * @param array<int, array<int, array<string, mixed>>> $overnightGroups
   *   Current overnight Office Hours groups.
   * @param array<int, string|null> $overnightInfo
   *   Current overnight group headings.
   * @param array<int, array<string, mixed>> $daily
   *   Daily Office Hours rows from one PTV service hour block.
   * @param array<int, array<string, mixed>> $overnight
   *   Overnight Office Hours rows from one PTV service hour block.
   * @param string|null $info
   *   Localized PTV service hour heading.
   */
  private static function appendRegularGroups(
    array &$dailyGroups,
    array &$dailyInfo,
    array &$overnightGroups,
    array &$overnightInfo,
    array $daily,
    array $overnight,
    ?string $info,
  ): void {
    $daily = array_values(array_unique($daily, SORT_REGULAR));
    $overnight = array_values(array_unique($overnight, SORT_REGULAR));
    $info = self::normalizeString($info);

    if ($daily !== []) {
      $dailyGroups[] = $daily;
      $dailyInfo[] = $info;
    }

    if ($overnight !== []) {
      $overnightGroups[] = $overnight;
      $overnightInfo[] = $info;
    }
  }

  /**
   * Adds one interval to daily or overnight Office Hours values.
   */
  private static function addInterval(
    array &$daily,
    array &$overnight,
    int $day,
    string $start,
    string $end,
    string $comment,
  ): void {
    $startValue = self::formatOfficeTime($start);
    $endValue = self::formatOfficeTime($end);

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
   * Maps one date range service hour into an exception value.
   *
   * @return array{sort: string|null, is_past: bool, info: string}|null
   *   A sortable exception value, or NULL when no usable text exists.
   */
  private static function mapDateRangeException(
    object $hour,
    string $language,
    \DateTimeImmutable $referenceDate,
  ): ?array {
    $type = self::getString($hour, 'getType');
    $fromDate = self::getDate(self::getValue($hour, 'getFromDate'));
    $toDate = self::getDate(self::getValue($hour, 'getToDate'));

    $description = self::joinText([
      self::getLocalizedString($hour, 'getHeading', $language),
      self::getLocalizedString(
        $hour,
        'getAdditionalInformation',
        $language
      ),
    ]);

    if ($type === 'ContinuousDateRangeServiceHour') {
      $schedule = self::formatContinuousHours($hour, $language);
    } else {
      $schedule = self::formatWeeklyHours(
        self::getObject($hour, 'getWeeklyHours'),
        $language,
        $type === 'WeeklyDateRangeServiceHour'
          || (
            $fromDate !== NULL
            && $toDate !== NULL
            && $fromDate->format('Y-m-d') !== $toDate->format('Y-m-d')
          )
      );
    }

    $info = self::joinText([
      $description,
      self::formatDateRange($fromDate, $toDate),
      $schedule,
    ]);

    if ($info === '') {
      return NULL;
    }

    $sortDate = $fromDate ?? $toDate;
    $referenceSort = $referenceDate->format('Y-m-d');
    $fromSort = $fromDate?->format('Y-m-d');
    $toSort = $toDate?->format('Y-m-d');
    $sortValue = $sortDate?->format('Y-m-d');
    $isPast = $toSort !== NULL
      ? $toSort < $referenceSort
      : ($sortValue !== NULL && $sortValue < $referenceSort);
    if ($isPast) {
      return NULL;
    }
    if (
      !$isPast
      && $fromSort !== NULL
      && $fromSort < $referenceSort
    ) {
      $sortDate = $referenceDate;
    }

    return [
      'sort' => $sortDate?->format('Y-m-d'),
      'is_past' => FALSE,
      'info' => $info,
    ];
  }

  /**
   * Maps holiday service hours into exception values.
   *
   * @return array<int, array{sort: string|null, is_past: bool, info: string}>
   *   Holiday exception values.
   */
  private static function mapHolidayExceptions(
    object $holidayHours,
    string $language,
    \DateTimeImmutable $referenceDate,
  ): array {
    $exceptions = [];

    foreach (self::getArray($holidayHours, 'getHolidays') as $holiday) {
      if (!is_object($holiday)) {
        continue;
      }

      $name = self::getString($holiday, 'getHolidayName');

      if ($name === NULL) {
        continue;
      }

      $date = self::getNextHolidayDate($name, $referenceDate);
      $times = [];

      foreach (self::getArray($holiday, 'getHours') as $hour) {
        if (!is_object($hour)) {
          continue;
        }

        $time = self::formatStatusTime(
          self::getString($hour, 'getStart'),
          self::getString($hour, 'getEnd'),
          self::getString($hour, 'getOpeningStatus'),
          self::getLocalizedString(
            $hour,
            'getAdditionalInformation',
            $language
          ),
          $language
        );

        if ($time !== '') {
          $times[] = $time;
        }
      }

      $exceptions[] = [
        'sort' => $date?->format('Y-m-d'),
        'is_past' => FALSE,
        'info' => self::joinText([
          self::getHolidayLabel($name, $language),
          $date !== NULL ? self::formatDateRange($date, $date) : NULL,
          implode(', ', array_unique($times)),
        ]),
      ];
    }

    return array_filter(
      $exceptions,
      static fn(array $item): bool => $item['info'] !== ''
    );
  }

  /**
   * Resolves a recurring PTV holiday to its next calendar occurrence.
   *
   * PTV provides only the holiday name, such as NewYearDay, without a year.
   * Yasumi provides the holiday date for a given year. If that date has
   * already passed, use the occurrence from the following year.
   */
  private static function getNextHolidayDate(
    string $holidayName,
    \DateTimeImmutable $referenceDate,
  ): ?\DateTimeImmutable {
    $year = (int) $referenceDate->format('Y');
    $date = self::getHolidayDateForYear($holidayName, $year);

    if ($date === NULL) {
      return NULL;
    }

    return $date < $referenceDate
      ? self::getHolidayDateForYear($holidayName, $year + 1)
      : $date;
  }

  /**
   * Resolves a PTV holiday name to its calendar date for the given year.
   *
   * Yasumi handles the Finnish holiday calendar, including moving holidays
   * such as Easter, Ascension Day, Midsummer Day and All Saints' Day.
   * PTV-specific eve values are derived from the corresponding Yasumi holiday.
   */
  private static function getHolidayDateForYear(
    string $holidayName,
    int $year,
  ): ?\DateTimeImmutable {
    return match ($holidayName) {
      'NewYearEve' => self::getYasumiHolidayDate('newYearsDay', $year + 1)
        ?->modify('-1 day'),
      'MaundyThursday' => self::getYasumiHolidayDate('goodFriday', $year)
        ?->modify('-1 day'),
      'MayDayEve' => self::getYasumiHolidayDate(
        'internationalWorkersDay',
        $year
      )?->modify('-1 day'),
      'MidsummerEve' => self::getYasumiHolidayDate('stJohnsDay', $year)
        ?->modify('-1 day'),
      'ChristmasEve' => self::getYasumiHolidayDate('christmasDay', $year)
        ?->modify('-1 day'),
      default => isset(self::YASUMI_HOLIDAY_KEYS[$holidayName])
        ? self::getYasumiHolidayDate(
          self::YASUMI_HOLIDAY_KEYS[$holidayName],
          $year
        )
        : NULL,
    };
  }

  /**
   * Gets one holiday date from Yasumi's Finland provider.
   */
  private static function getYasumiHolidayDate(
    string $holidayKey,
    int $year,
  ): ?\DateTimeImmutable {
    static $providers = [];

    $provider = $providers[$year] ??= Yasumi::create('Finland', $year);
    $holiday = $provider->getHoliday($holidayKey);

    return $holiday !== NULL
      ? \DateTimeImmutable::createFromInterface($holiday)
      : NULL;
  }

  /**
   * Formats weekly service hours as exception text.
   */
  private static function formatWeeklyHours(
    ?object $weeklyHours,
    string $language,
    bool $showWeekdays,
  ): string {
    if ($weeklyHours === NULL) {
      return '';
    }

    $days = [];

    foreach (self::WEEKDAYS as $weekday => $day) {
      $weekdayHours = self::getObject(
        $weeklyHours,
        'get' . $weekday
      );

      if ($weekdayHours === NULL) {
        continue;
      }

      $times = [];

      foreach (self::getArray($weekdayHours, 'getHours') as $hour) {
        if (!is_object($hour)) {
          continue;
        }

        $time = self::formatStatusTime(
          self::getString($hour, 'getStart'),
          self::getString($hour, 'getEnd'),
          self::getString($hour, 'getOpeningStatus'),
          self::getLocalizedString(
            $hour,
            'getAdditionalInformation',
            $language
          ),
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
  private static function formatContinuousHours(
    object $hour,
    string $language,
  ): string {
    $time = self::formatStatusTime(
      self::getString($hour, 'getFromTime'),
      self::getString($hour, 'getToTime'),
      self::getString($hour, 'getOpeningStatus'),
      NULL,
      $language
    );

    $fromDay = self::getString($hour, 'getFromDay');
    $toDay = self::getString($hour, 'getToDay');

    if ($time === '' || $fromDay === NULL || $toDay === NULL) {
      return $time;
    }

    $days = $fromDay === $toDay
      ? self::getWeekdayLabel($fromDay, $language)
      : self::getWeekdayLabel($fromDay, $language)
      . '–'
      . self::getWeekdayLabel($toDay, $language);

    return $days . ' ' . $time;
  }

  /**
   * Formats a status and time interval for display.
   */
  private static function formatStatusTime(
    ?string $start,
    ?string $end,
    ?string $status,
    ?string $additionalInformation,
    string $language,
  ): string {
    if ($status === 'Closed') {
      return self::joinText([
        self::getClosedLabel($language),
        $additionalInformation,
      ]);
    }

    return self::joinText([
      self::formatTimeRange($start, $end),
      $status === 'OpenOnReservation'
        ? self::getReservationLabel($language)
        : NULL,
      $additionalInformation,
    ]);
  }

  /**
   * Formats a date range for display.
   */
  private static function formatDateRange(
    ?\DateTimeImmutable $from,
    ?\DateTimeImmutable $to,
  ): string {
    if ($from === NULL && $to === NULL) {
      return '';
    }

    if (
      $from !== NULL
      && $to !== NULL
      && $from->format('Y-m-d') !== $to->format('Y-m-d')
    ) {
      return $from->format('j.n.Y') . '—' . $to->format('j.n.Y');
    }

    return ($from ?? $to)->format('j.n.Y');
  }

  /**
   * Formats a time range for display.
   */
  private static function formatTimeRange(
    ?string $start,
    ?string $end,
  ): string {
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
  private static function formatOfficeTime(string $time): ?string
  {
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
      return NULL;
    }

    return str_pad((string) (int) $matches[1], 2, '0', STR_PAD_LEFT)
      . $matches[2];
  }

  /**
   * Converts HH:MM to a display value.
   */
  private static function formatDisplayTime(?string $time): ?string
  {
    if (
      $time === NULL
      || !preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)
    ) {
      return NULL;
    }

    return (int) $matches[1] . '.' . $matches[2];
  }

  /**
   * Gets weekday numbers in an inclusive weekday range.
   *
   * @return int[]
   *   Weekday numbers where Sunday is 0 and Saturday is 6.
   */
  private static function getDayRange(string $from, string $to): array
  {
    if (!isset(self::WEEKDAYS[$from], self::WEEKDAYS[$to])) {
      return [];
    }

    $days = [];
    $day = self::WEEKDAYS[$from];
    $end = self::WEEKDAYS[$to];

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
   * Builds an Office Hours comment.
   */
  private static function buildComment(
    ?string $additionalInformation,
    ?string $status,
    string $language,
  ): string {
    return self::joinText([
      $additionalInformation,
      $status === 'OpenOnReservation'
        ? self::getReservationLabel($language)
        : NULL,
    ]);
  }

  /**
   * Gets a value from a generated DTO getter.
   */
  private static function getValue(object $model, string $getter): mixed
  {
    return method_exists($model, $getter)
      ? $model->{$getter}()
      : NULL;
  }

  /**
   * Gets an object from a generated DTO getter.
   */
  private static function getObject(
    object $model,
    string $getter,
  ): ?object {
    $value = self::getValue($model, $getter);

    return is_object($value) ? $value : NULL;
  }

  /**
   * Gets an array from a generated DTO getter.
   *
   * @return array<int, mixed>
   *   Getter values.
   */
  private static function getArray(
    object $model,
    string $getter,
  ): array {
    $value = self::getValue($model, $getter);

    if ($value instanceof \ArrayObject) {
      return $value->getArrayCopy();
    }

    return is_array($value) ? $value : [];
  }

  /**
   * Gets a normalized string from a generated DTO getter.
   */
  private static function getString(
    object $model,
    string $getter,
  ): ?string {
    return self::normalizeString(self::getValue($model, $getter));
  }

  /**
   * Gets a localized string from a generated DTO.
   */
  private static function getLocalizedString(
    object $model,
    string $getter,
    string $language,
  ): ?string {
    $localized = self::getObject($model, $getter);
    $localizedGetter = self::LANGUAGE_GETTERS[$language] ?? NULL;

    if ($localized === NULL || $localizedGetter === NULL) {
      return NULL;
    }

    return self::getString($localized, $localizedGetter);
  }

  /**
   * Normalizes a scalar or stringable value.
   */
  private static function normalizeString(mixed $value): ?string
  {
    if ($value === NULL) {
      return NULL;
    }

    if (is_string($value)) {
      $value = trim($value);

      return $value === '' ? NULL : $value;
    }

    if (is_object($value) && method_exists($value, '__toString')) {
      $value = trim((string) $value);

      return $value === '' ? NULL : $value;
    }

    return NULL;
  }

  /**
   * Converts a generated DTO date value to an immutable date.
   */
  private static function getDate(mixed $value): ?\DateTimeImmutable
  {
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
    } catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Joins non-empty values without duplicates.
   */
  private static function joinText(array $parts): string
  {
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
  private static function getWeekdayLabel(
    string $weekday,
    string $language,
  ): string {
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
    ];

    return $labels[$language][$weekday]
      ?? $labels['fi'][$weekday]
      ?? $weekday;
  }

  /**
   * Gets a localized closed label.
   */
  private static function getClosedLabel(string $language): string
  {
    return [
      'fi' => 'suljettu',
      'en' => 'closed',
    ][$language] ?? 'suljettu';
  }

  /**
   * Gets a localized appointment-only label.
   */
  private static function getReservationLabel(string $language): string
  {
    return [
      'fi' => 'ajanvarauksella',
      'en' => 'by appointment',
    ][$language] ?? 'ajanvarauksella';
  }

  /**
   * Gets a localized holiday label.
   */
  private static function getHolidayLabel(
    string $holiday,
    string $language,
  ): string {
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
    ];

    return $labels[$language][$holiday]
      ?? $labels['fi'][$holiday]
      ?? $holiday;
  }
}
