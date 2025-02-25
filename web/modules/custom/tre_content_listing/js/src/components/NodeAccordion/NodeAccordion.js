import React, { useState, useEffect } from "react";
import {
  Title,
  PageLink,
  LinkText,
  NodeAccordionContainer,
  NodeHeader,
  NodeDetails,
  NodeImage,
  ToggleIcon,
  NodeTextBody,
  LinkIcon,
} from "./NodeAccordion.styles";

const NodeAccordion = ({ node }) => {
  const [open, setOpen] = useState(false);

  const controlId = `node-accordion-control-${node.id}`;
  const contentId = `node-accordion-content-${node.id}`;

  const handleKeyDown = (e) => {
    if (e.key === "Enter") {
      setOpen(!open);
    }
  };

  useEffect(() => {
    if (open && node.text_body) {
      if (
        Drupal &&
        Drupal.behaviors &&
        Drupal.behaviors.externalLinkContainer
      ) {
        const context = document.getElementById(contentId);
        if (context) {
          Drupal.behaviors.externalLinkContainer.attach(context);
        }
      }
    }
  }, [open, node.text_body]);

  return (
    <NodeAccordionContainer open={open}>
      <NodeHeader
        onClick={() => setOpen(!open)}
        onKeyDown={handleKeyDown}
        open={open}
        tabIndex="0"
        aria-controls={contentId}
        aria-expanded={open}
        id={controlId}
        title={node.title}
      >
        <Title>{node.title}</Title>

        <ToggleIcon isActive={open}>
          <use xlinkHref="/themes/custom/tampere/dist/main-site-icons.svg?20250221#arrow-plain-new" />
        </ToggleIcon>
      </NodeHeader>

      {open && (
        <NodeDetails id={contentId} role="region" aria-labelledby={controlId}>
          {node.text_body && (
            <NodeTextBody
              className="text-long"
              dangerouslySetInnerHTML={{ __html: node.text_body }}
            ></NodeTextBody>
          )}

          {node.link_url && (
            <PageLink href={node.link_url}>
              <LinkText>{node.link_title}</LinkText>
              <LinkIcon>
                <use xlinkHref="/themes/custom/tampere/dist/main-site-icons.svg?20250221#arrow" />
              </LinkIcon>
            </PageLink>
          )}

          {node.listing_image && (
            <NodeImage>
              <img
                src={node.listing_image}
                alt={node.title}
                style={{ maxWidth: "100%" }}
              />
            </NodeImage>
          )}

          {node.attachment_file && (
            <PageLink href={node.attachment_file}>
              <LinkText>{node.attachment_file_name}</LinkText>
              <LinkIcon>
                <use xlinkHref="/themes/custom/tampere/dist/main-site-icons.svg?20250221#download" />
              </LinkIcon>
            </PageLink>
          )}
        </NodeDetails>
      )}
    </NodeAccordionContainer>
  );
};

export default NodeAccordion;
