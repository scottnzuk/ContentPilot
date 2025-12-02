#!/usr/bin/env python3
"""
AI Auto News Poster - Humanizer Script

This script provides offline AI content humanization using the humano Python package.
It processes text from stdin and outputs humanized content to stdout.

Usage:
    echo '{"text": "content to humanize", "strength": "medium", "personality": ""}' | python3 humanizer.py

Requirements:
    - Python 3.6+
    - humano package (pip install humano)

@package AI_Auto_News_Poster
@version 1.2.0
"""

import sys
import json
import os
from typing import Dict, Any

def main():
    """Main entry point for the humanizer script."""
    try:
        # Read input from stdin
        input_data = read_input()
        
        # Extract parameters
        text = input_data.get('text', '')
        strength = input_data.get('strength', 'medium')
        personality = input_data.get('personality', '')
        
        # Validate inputs
        if not text.strip():
            raise ValueError("No text provided for humanization")
        
        # Process with humano
        humanized_text = process_with_humano(text, strength, personality)
        
        # Output result
        output_result(humanized_text)
        
    except Exception as e:
        # Handle errors gracefully
        error_message = str(e)
        print(f"Error: {error_message}", file=sys.stderr)
        sys.exit(1)

def read_input() -> Dict[str, Any]:
    """Read and parse JSON input from stdin."""
    try:
        # Read all input from stdin
        stdin_data = sys.stdin.read().strip()
        
        if not stdin_data:
            raise ValueError("No input received from stdin")
        
        # Parse JSON
        input_data = json.loads(stdin_data)
        return input_data
        
    except json.JSONDecodeError as e:
        raise ValueError(f"Invalid JSON input: {e}")
    except Exception as e:
        raise ValueError(f"Failed to read input: {e}")

def process_with_humano(text: str, strength: str, personality: str) -> str:
    """Process text using the humano library."""
    try:
        # Import humano - this may raise ImportError if not installed
        try:
            from humano import humanize
        except ImportError:
            raise ImportError(
                "The 'humano' package is not installed. "
                "Install it with: pip install humano"
            )
        
        # Validate strength parameter
        valid_strengths = ['low', 'medium', 'high', 'maximum']
        if strength not in valid_strengths:
            strength = 'medium'
        
        # Prepare parameters for humano
        humano_params = {
            'strength': strength,
            'personality': personality.strip() if personality.strip() else None
        }
        
        # Remove None values
        humano_params = {k: v for k, v in humano_params.items() if v is not None}
        
        # Process text with humano
        # Note: The actual humano API may vary, this is a best-guess implementation
        humanized_text = humanize(text, **humano_params)
        
        # Ensure we get a string result
        if not isinstance(humanized_text, str):
            humanized_text = str(humanized_text)
        
        return humanized_text.strip()
        
    except ImportError as e:
        raise ImportError(str(e))
    except Exception as e:
        # Handle any humano-specific errors
        raise RuntimeError(f"Humano processing failed: {e}")

def output_result(humanized_text: str):
    """Output the result in JSON format."""
    try:
        # Prepare output data
        output_data = {
            'humanized_text': humanized_text,
            'timestamp': get_current_timestamp(),
            'success': True
        }
        
        # Output as JSON
        print(json.dumps(output_data, ensure_ascii=False, separators=(',', ':')))
        
    except Exception as e:
        print(f"Error: Failed to output result: {e}", file=sys.stderr)
        sys.exit(1)

def get_current_timestamp() -> str:
    """Get current timestamp in ISO format."""
    try:
        from datetime import datetime
        return datetime.now().isoformat()
    except:
        return "unknown"

def check_dependencies():
    """Check if required dependencies are available."""
    issues = []
    
    # Check Python version
    if sys.version_info < (3, 6):
        issues.append("Python 3.6+ required")
    
    # Check humano package
    try:
        import humano
    except ImportError:
        issues.append("humano package not installed (pip install humano)")
    
    return issues

def print_dependencies_help():
    """Print installation instructions for missing dependencies."""
    print("Dependencies Check Results:", file=sys.stderr)
    
    issues = check_dependencies()
    if not issues:
        print("✓ All dependencies are available", file=sys.stderr)
        return
    
    print("✗ Missing dependencies:", file=sys.stderr)
    for issue in issues:
        print(f"  - {issue}", file=sys.stderr)
    
    print("\nInstallation Instructions:", file=sys.stderr)
    print("1. Install Python 3.6+ if not already installed", file=sys.stderr)
    print("2. Install the humano package:", file=sys.stderr)
    print("   pip install humano", file=sys.stderr)
    print("   OR for Python 3 specifically:", file=sys.stderr)
    print("   python3 -m pip install humano", file=sys.stderr)
    print("\nFor virtual environments:", file=sys.stderr)
    print("   source venv/bin/activate", file=sys.stderr)
    print("   pip install humano", file=sys.stderr)

if __name__ == "__main__":
    # Check if help is requested
    if len(sys.argv) > 1 and sys.argv[1] in ['--help', '-h', 'help']:
        print_dependencies_help()
        sys.exit(0)
    
    # Run main function
    main()