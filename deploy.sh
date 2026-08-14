#!/bin/bash

# Service-specific Docker Swarm Deployment Script
# This script updates a single service in the Docker Swarm cluster.

set -e  # Exit on any error

# Color definitions
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration variables (adjust per service)
STACK_NAME="volvicon"
SERVICE_NAME="api"  # Change this for each service: api, frontend, manage, admin, help
NODE_USER="volvicon"
MANAGER_HOST="87.106.56.165"  # The swarm manager hostname
NODE_HOST="${MANAGER_HOST}"  # Load image on the manager node
IMAGES_DIR="../deploy/images"
IMAGE_NAME="${STACK_NAME}-${SERVICE_NAME}:latest"
TAR_FILE="${IMAGES_DIR}/${STACK_NAME}-${SERVICE_NAME}_latest.tar"
SWARM_SERVICE_NAME="${STACK_NAME}_${SERVICE_NAME}"

# Ensure that the local git repository is up to date
update_src() {
    git pull origin main
}

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to build Docker image
build_image() {
    print_status "Building Docker image ${IMAGE_NAME}..."

    docker build -t ${IMAGE_NAME} .
    print_success "Built ${IMAGE_NAME}"
}

# Function to save image to tar file
save_image() {
    print_status "Saving image to tar file..."

    mkdir -p ${IMAGES_DIR}
    docker save ${IMAGE_NAME} -o ${TAR_FILE}
    print_success "Saved ${IMAGE_NAME} to ${TAR_FILE}"
}

# Function to copy image to node
copy_to_node() {
    print_status "Copying image to ${NODE_HOST}..."

    scp ${TAR_FILE} ${NODE_USER}@${NODE_HOST}:/tmp/
    print_success "Copied image to ${NODE_HOST}"
}

# Function to load image on node
load_on_node() {
    print_status "Loading image on ${NODE_HOST}..."

    ssh ${NODE_USER}@${NODE_HOST} "
        set -e
        echo 'Loading ${IMAGE_NAME}...'
        docker load -i /tmp/${STACK_NAME}-${SERVICE_NAME}_latest.tar
        echo 'Verifying image...'
        docker images | grep '${STACK_NAME}-${SERVICE_NAME}'
        echo 'Image loaded successfully'
    "

    if [ $? -eq 0 ]; then
        print_success "Image loaded on ${NODE_HOST}"
    else
        print_error "Failed to load image on ${NODE_HOST}"
        exit 1
    fi
}

# Function to update service
update_service() {
    print_status "Updating service ${SWARM_SERVICE_NAME}..."

    ssh ${NODE_USER}@${MANAGER_HOST} "docker service update --force --image ${IMAGE_NAME} ${SWARM_SERVICE_NAME}"

    if [ $? -eq 0 ]; then
        print_success "Service ${SWARM_SERVICE_NAME} updated successfully"
    else
        print_error "Failed to update service ${SWARM_SERVICE_NAME}"
        exit 1
    fi
}

# Main execution
main() {
    echo -e "${CYAN}========================================${NC}"
    echo -e "${CYAN}  Service Deployment Script${NC}"
    echo -e "${CYAN}  Service: ${SERVICE_NAME}${NC}"
    echo -e "${CYAN}  Image: ${IMAGE_NAME}${NC}"
    echo -e "${CYAN}========================================${NC}"

    update_src
    build_image
    save_image
    copy_to_node
    load_on_node
    update_service

    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  Service ${SERVICE_NAME} updated successfully!${NC}"
    echo -e "${GREEN}========================================${NC}"
}

# Run main function
main "$@"
