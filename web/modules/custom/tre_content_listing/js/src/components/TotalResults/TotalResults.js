import React from "react";
import { StyledResults } from "./TotalResults.styles";

const TotalResults = ({ totalResults }) => {
  return (
    <StyledResults>
      {Drupal.t("Total @total results", { "@total": totalResults })}
    </StyledResults>
  );
};
export default TotalResults;
