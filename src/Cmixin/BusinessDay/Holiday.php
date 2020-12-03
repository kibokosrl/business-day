<?php

namespace Cmixin\BusinessDay;

use Carbon\Carbon;
use Cmixin\BusinessDay;
use Cmixin\BusinessDay\Calculator\MixinConfigPropagator;
use Exception;
use SplObjectStorage;

class Holiday extends YearCrawler
{
    use HolidayData;

    const DEFAULT_HOLIDAY_LOCALE = 'en';

    /**
     * @var array
     */
    public $holidayNames = [];

    /**
     * @var callable|null
     */
    public $holidayGetter = null;

    /**
     * @var SplObjectStorage<object,callable>|null
     */
    public $holidayGetters = null;

    /**
     * Set the strategy to get the holiday ID from a date object.
     *
     * @return \Closure
     */
    public function setHolidayGetter()
    {
        $mixin = $this;

        /**
         * Set the strategy to get the holiday ID from a date object.
         *
         * @param callable|null $holidayGetter
         * @param object|null   $self
         *
         * @return $this|null
         */
        return function (?callable $holidayGetter, $self = null) use ($mixin) {
            return MixinConfigPropagator::setHolidayGetter(
                $mixin,
                isset($this) && $this !== $mixin ? $this : $self,
                $holidayGetter
            );
        };
    }

    /**
     * Get the identifier of the current holiday or false if it's not a holiday.
     *
     * @return \Closure
     */
    public function getHolidayId()
    {
        $mixin = $this;

        /**
         * Get the identifier of the current holiday or false if it's not a holiday.
         *
         * @return string|false
         */
        return function ($self = null) use ($mixin) {
            $carbonClass = @get_class() ?: Emulator::getClass(new Exception());

            $date = isset($this) && $this !== $mixin ? $this : null;

            /** @var Carbon|BusinessDay $self */
            $self = $carbonClass::getThisOrToday($self, $date);

            $fallback = function () use ($self) {
                $date = $self->format('d/m');
                $year = $self->year;

                $next = $self->getYearHolidaysNextFunction($year, 'string', $self);

                while ($data = $next()) {
                    [$holidayId, $holiday] = $data;

                    if ($holiday && $date.(strlen($holiday) > 5 ? "/$year" : '') === $holiday) {
                        return $holidayId;
                    }
                }

                return false;
            };

            $holidayGetter = MixinConfigPropagator::getHolidayGetter($mixin, $date ?? $self);

            return $holidayGetter
                ? $holidayGetter($mixin->holidaysRegion, $self, $fallback)
                : $fallback();
        };
    }

    /**
     * Checks the date to see if it is a holiday.
     *
     * @return \Closure
     */
    public function isHoliday()
    {
        $mixin = $this;

        /**
         * Checks the date to see if it is a holiday.
         *
         * @return bool
         */
        return function ($self = null) use ($mixin) {
            $carbonClass = @get_class() ?: Emulator::getClass(new Exception());

            /** @var Carbon|BusinessDay $self */
            $self = $carbonClass::getThisOrToday($self, isset($this) && $this !== $mixin ? $this : null);

            return $self->getHolidayId() !== false;
        };
    }

    /**
     * Get the holidays in the given language.
     *
     * @return \Closure
     */
    public function getHolidayNamesDictionary()
    {
        $mixin = $this;
        $defaultLocale = static::DEFAULT_HOLIDAY_LOCALE;

        /**
         * Get the holidays in the given language.
         *
         * @param string $locale language
         *
         * @return array
         */
        return function ($locale) use ($mixin, $defaultLocale) {
            if (isset($mixin->holidayNames[$locale])) {
                return $mixin->holidayNames[$locale] ?: $mixin->holidayNames[$defaultLocale];
            }

            $file = __DIR__."/../HolidayNames/$locale.php";

            if (!file_exists($file)) {
                $mixin->holidayNames[$locale] = false;
                $locale = $defaultLocale;
                $file = __DIR__."/../HolidayNames/$locale.php";

                if (isset($mixin->holidayNames[$locale])) {
                    return $mixin->holidayNames[$locale];
                }
            }

            return $mixin->holidayNames[$locale] = include $file;
        };
    }

    /**
     * Get the name of the current holiday (using the locale given in parameter or the current date locale)
     * or false if it's not a holiday.
     *
     * @return \Closure
     */
    public function getHolidayName()
    {
        $mixin = $this;
        $dictionary = $this->getHolidayNamesDictionary();

        /**
         * Get the name of the current holiday (using the locale given in parameter or the current date locale)
         * or false if it's not a holiday.
         *
         * @param string $locale language ("en" by default)
         *
         * @return string|false
         */
        return function ($locale = null, $self = null) use ($mixin, $dictionary) {
            $carbonClass = @get_class() ?: Emulator::getClass(new Exception());

            [$locale, $self] = $carbonClass::swapDateTimeParam($locale, $self, null);

            /** @var Carbon|BusinessDay $self */
            $self = $carbonClass::getThisOrToday($self, isset($this) && $this !== $mixin ? $this : null);
            $holidayId = $self->getHolidayId();

            if ($holidayId === false) {
                return false;
            }

            if (!$locale) {
                $locale = (isset($self->locale) ? $self->locale : $carbonClass::getLocale()) ?: 'en';
            }

            /* @var string $holidayId */
            $names = $dictionary(preg_replace('/^([^_-]+)([_-].*)$/', '$1', $locale));

            return isset($names[$holidayId]) ? $names[$holidayId] : 'Unknown';
        };
    }
}
