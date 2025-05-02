FROM nginx:latest

# Remove default configuration
RUN rm -f /etc/nginx/conf.d/default.conf

# Copy custom configuration
COPY nginx.conf /etc/nginx/nginx.conf