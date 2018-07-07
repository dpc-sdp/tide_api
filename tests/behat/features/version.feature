@tide @jsonapi
Feature: API version

  Scenario: Request to API to get version
    Given I send a GET request to "api/v1"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "api_version" should be equal to "1.0"
    And the JSON node "links" should exist

