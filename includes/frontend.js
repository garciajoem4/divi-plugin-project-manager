// Frontend JavaScript for Divi Custom Modules
import React from 'react';
import { createRoot } from 'react-dom/client';
import ProjectManager from './modules/ProjectManager/ProjectManager';

// ProjectManager React Component Initialization
if (typeof window !== 'undefined') {
  document.addEventListener("DOMContentLoaded", function() {
    // Find all ProjectManager containers
    const containers = document.querySelectorAll(".dicm-project-manager");
    
    containers.forEach(function(container) {
      // Skip if already initialized or login required message
      if (container.hasAttribute("data-initialized") || container.classList.contains("pm-login-required")) {
        return;
      }
      
      // Get configuration from data attribute
      const configData = container.getAttribute("data-config");
      let config = {};
      
      try {
        config = configData ? JSON.parse(configData) : {};
      } catch (error) {
        console.error("ProjectManager: Failed to parse config data:", error);
        return;
      }
      
      // Clear existing content
      container.innerHTML = "";
      
      // Create React root and render component
      const root = createRoot(container);
      root.render(React.createElement(ProjectManager, {
        attrs: {
          config: JSON.stringify(config)
        }
      }));
      
      // Mark as initialized
      container.setAttribute("data-initialized", "true");
      
      console.log("ProjectManager: Component initialized successfully");
    });
  });
}
