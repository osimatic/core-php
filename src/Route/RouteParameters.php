<?php

namespace Osimatic\Route;

/**
 * Routing options used when calculating the distance and the duration of a route.
 * This class holds optional routing preferences such as transit modes, route avoidance options and real-time traffic.
 * Options that do not apply to the requested travel mode are ignored by the route provider.
 * @link https://developers.google.com/maps/documentation/routes/reference/rest/v2/TopLevel/computeRouteMatrix Routes API computeRouteMatrix reference
 */
class RouteParameters
{
	/**
	 * Array of transit travel modes to use for public transit routing.
	 * @var TransitTravelMode[]
	 */
	private array $transitModes = [];

	/**
	 * Whether to avoid toll roads in the route calculation.
	 * @var bool
	 */
	private bool $avoidTolls = false;

	/**
	 * Whether to avoid highways in the route calculation.
	 * @var bool
	 */
	private bool $avoidHighways = false;

	/**
	 * Whether to avoid ferries in the route calculation.
	 * @var bool
	 */
	private bool $avoidFerries = false;

	/**
	 * Whether to avoid indoor routes in walking directions.
	 * @var bool
	 */
	private bool $avoidIndoor = false;

	/**
	 * Whether the duration must take real-time traffic into account (only used with the DRIVE travel mode).
	 * @var bool
	 */
	private bool $trafficAware = false;

	/**
	 * Gets the array of preferred transit travel modes for public transit routing.
	 * @return TransitTravelMode[] Array of transit modes (BUS, SUBWAY, TRAIN, LIGHT_RAIL)
	 */
	public function getTransitModes(): array
	{
		return $this->transitModes;
	}

	/**
	 * Sets the preferred transit travel modes for public transit routing.
	 * @param TransitTravelMode[] $transitModes Array of transit modes to prefer (e.g., [TransitTravelMode::BUS, TransitTravelMode::SUBWAY])
	 * @return self Returns this instance for method chaining
	 */
	public function setTransitModes(array $transitModes): self
	{
		$this->transitModes = $transitModes;
		return $this;
	}

	/**
	 * Checks if toll roads should be avoided in the route calculation.
	 * @return bool True if avoiding tolls, false otherwise
	 */
	public function isAvoidTolls(): bool
	{
		return $this->avoidTolls;
	}

	/**
	 * Sets whether to avoid toll roads in the route calculation.
	 * @param bool $avoidTolls True to avoid toll roads, false to allow them
	 * @return self Returns this instance for method chaining
	 */
	public function setAvoidTolls(bool $avoidTolls): self
	{
		$this->avoidTolls = $avoidTolls;
		return $this;
	}

	/**
	 * Checks if highways should be avoided in the route calculation.
	 * @return bool True if avoiding highways, false otherwise
	 */
	public function isAvoidHighways(): bool
	{
		return $this->avoidHighways;
	}

	/**
	 * Sets whether to avoid highways in the route calculation.
	 * @param bool $avoidHighways True to avoid highways, false to allow them
	 * @return self Returns this instance for method chaining
	 */
	public function setAvoidHighways(bool $avoidHighways): self
	{
		$this->avoidHighways = $avoidHighways;
		return $this;
	}

	/**
	 * Checks if ferries should be avoided in the route calculation.
	 * @return bool True if avoiding ferries, false otherwise
	 */
	public function isAvoidFerries(): bool
	{
		return $this->avoidFerries;
	}

	/**
	 * Sets whether to avoid ferries in the route calculation.
	 * @param bool $avoidFerries True to avoid ferries, false to allow them
	 * @return self Returns this instance for method chaining
	 */
	public function setAvoidFerries(bool $avoidFerries): self
	{
		$this->avoidFerries = $avoidFerries;
		return $this;
	}

	/**
	 * Checks if indoor routes should be avoided in walking directions.
	 * @return bool True if avoiding indoor routes, false otherwise
	 */
	public function isAvoidIndoor(): bool
	{
		return $this->avoidIndoor;
	}

	/**
	 * Sets whether to avoid indoor routes in walking directions.
	 * @param bool $avoidIndoor True to avoid indoor routes, false to allow them
	 * @return self Returns this instance for method chaining
	 */
	public function setAvoidIndoor(bool $avoidIndoor): self
	{
		$this->avoidIndoor = $avoidIndoor;
		return $this;
	}

	/**
	 * Checks if the duration must take real-time traffic into account.
	 * @return bool True if traffic is taken into account, false otherwise
	 */
	public function isTrafficAware(): bool
	{
		return $this->trafficAware;
	}

	/**
	 * Sets whether the duration must take real-time traffic into account (only used with the DRIVE travel mode).
	 * @param bool $trafficAware True to take traffic into account, false to ignore it
	 * @return self Returns this instance for method chaining
	 */
	public function setTrafficAware(bool $trafficAware): self
	{
		$this->trafficAware = $trafficAware;
		return $this;
	}

	/**
	 * Convenience method to enable avoiding toll roads.
	 * @return self Returns this instance for method chaining
	 */
	public function avoidTolls(): self
	{
		return $this->setAvoidTolls(true);
	}

	/**
	 * Convenience method to enable avoiding highways.
	 * @return self Returns this instance for method chaining
	 */
	public function avoidHighways(): self
	{
		return $this->setAvoidHighways(true);
	}

	/**
	 * Convenience method to enable avoiding ferries.
	 * @return self Returns this instance for method chaining
	 */
	public function avoidFerries(): self
	{
		return $this->setAvoidFerries(true);
	}

	/**
	 * Convenience method to enable avoiding indoor routes.
	 * @return self Returns this instance for method chaining
	 */
	public function avoidIndoor(): self
	{
		return $this->setAvoidIndoor(true);
	}

}