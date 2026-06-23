# frontend/Dockerfile — builds the Angular development container.
# This installs Node.js, Angular CLI, and runs the dev server.
# We use the official Node.js image as the base — it includes npm.

FROM node:20-alpine

# Install Angular CLI globally so we can run "ng" commands.
# --global means it's available anywhere in the container, not just
# in one project folder.
RUN npm install -g @angular/cli@17

# Set the working directory inside the container.
# All subsequent commands run from here.
WORKDIR /app

# Copy package.json first (before copying all source files).
# This is a Docker optimization: Docker caches each step. If we copy
# package.json first and run npm install, Docker only re-runs npm install
# when package.json changes — not on every source file change.
COPY package*.json ./

# Install all Node.js dependencies listed in package.json.
# This creates the node_modules/ folder inside the container.
RUN npm install

# Copy the rest of the Angular source files into the container.
COPY . .

# Expose port 4200 — Angular's default dev server port.
EXPOSE 4200

# Start Angular's development server when the container boots.
# --host 0.0.0.0 makes it listen on all network interfaces,
#   not just localhost — required for Docker to forward the port.
# --poll 500 makes Angular check for file changes every 500ms
#   (needed on Windows where file-change events don't work reliably in Docker).
CMD ["ng", "serve", "--host", "0.0.0.0", "--poll", "500"]
